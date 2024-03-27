<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Backend\Payments\PaymentsController;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product; 

use App\Models\Country;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Currency;
use App\Models\LogisticZone;
use App\Models\LogisticZoneCity;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderItem;
use App\Models\RewardPoint;
use App\Models\ScheduledDeliveryTimeList;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Http\Request;
use Notification;
use Config;
use Session;
use Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\UserAddress;


class CheckoutController extends Controller
{
    # checkout
    public function index()
    {
        $carts = Cart::where('user_id', auth()->user()->id)->where('location_id', session('stock_location_id'))->get();

        if (count($carts) > 0) {
            checkCouponValidityForCheckout($carts);
        }

        $user = auth()->user();
        $addresses = $user->addresses()->latest()->get();

        $countries = Country::isActive()->get();

        return getView('pages.checkout.checkout', [
            'carts'     => $carts,
            'user'      => $user,
            'addresses' => $addresses,
            'countries' => $countries,
        ]);
    }

    # checkout logistic
    public function getLogistic(Request $request)
    {
        $logisticZoneCities = LogisticZoneCity::where('city_id', $request->city_id)->distinct('logistic_id')->get();
        return [
            'logistics' => getViewRender('inc.logistics', ['logisticZoneCities' => $logisticZoneCities]),
            'summary'   => getViewRender('pages.partials.checkout.orderSummary', ['carts' => Cart::where('user_id', auth()->user()->id)->where('location_id', session('stock_location_id'))->get()])
        ];
    }

    # checkout shipping amount
    public function getShippingAmount(Request $request)
    {
        $carts              = Cart::where('user_id', auth()->user()->id)->where('location_id', session('stock_location_id'))->get();
        $logisticZone       = LogisticZone::find((int)$request->logistic_zone_id);
        $shippingAmount     = $logisticZone->standard_delivery_charge;
        return getViewRender('pages.partials.checkout.orderSummary', ['carts' => $carts, 'shippingAmount' => $shippingAmount]);
    }

    # complete checkout process
    public function complete(Request $request)
    {
        $user = auth()->user();
        $userId = $user->id;
        $carts  = Cart::where('user_id', $userId)->where('location_id', session('stock_location_id'))->get();

        if (count($carts) > 0) {

            # check if coupon applied -> validate coupon
            $couponResponse = checkCouponValidityForCheckout($carts);
            if ($couponResponse['status'] == false) {
                flash($couponResponse['message'])->error();
                return back();
            }

            # check carts available stock -- todo::[update version] -> run this check while storing OrderItems
            foreach ($carts as $cart) {
                
                $product = $cart->product_variation->product;

                $apiUrl = env('API_CATEGORIES_URL');
        
                if (Auth::check() && Auth::user()->user_type == 'customer')
            {
            $response = Http::get($apiUrl . 'ListeDePrixWeb/' . Auth::user()->CODETIERS);
            }else{
            
                $response = Http::get($apiUrl . 'ListeDePrixWeb/');
        
            }
                $produitsApi = $response->json();
        
                
                
                
                foreach ($produitsApi as $produitApi) {
                    
                    
                    $apiStock = $produitApi['StockActual'];
                   
        
                    if($produitApi['codeabarre'] == $product->slug ){
        
                     
                        $product->stock_qty = $apiStock;
                       
        
                    }
                }


                if($product->max_purchase_qty >= $cart->qty && $cart->qty >= $product->min_purchase_qty){
                    $productVariationStock =  $product->stock_qty ;
                    if ($cart->qty > $productVariationStock) {
                        $message = $cart->product_variation->product->collectLocalization('name') . ' ' . localize('Est en rupture de stoc');
                        flash($message)->error();
                        return back();
                    }
                }else{ 
                    $message = localize('La quantité de commande minimale et maximale est '). $product->min_purchase_qty. ' & ' . $product->max_purchase_qty .' '. localize(' pour ce produit est: '). $cart->product_variation->product->collectLocalization('name');

                    flash($message)->error();
                    return back();
                } 
            }
            


            # create new order group
            $orderGroup                                     = new OrderGroup;
            $orderGroup->user_id                            = $userId;
            $orderGroup->shipping_address_id                = $request->shipping_address_id;
            $orderGroup->billing_address_id                 = $request->billing_address_id;
            $orderGroup->location_id                        = session('stock_location_id');
            $orderGroup->phone_no                           = $request->phone;
            $orderGroup->alternative_phone_no               = $request->alternative_phone;
            $orderGroup->sub_total_amount                   = getSubTotal($carts, false, '', false);
            $orderGroup->total_tax_amount                   = getTotalTax($carts);
            $orderGroup->total_coupon_discount_amount       = 0;
            if (getCoupon() != '') {
                # todo::[for eCommerce] handle coupon for multi vendor
                $orderGroup->total_coupon_discount_amount   = getCouponDiscount(getSubTotal($carts, false), getCoupon());
                # [done->codes below] increase coupon usage counter after successful order
            }
            $logisticZone = LogisticZone::where('id', $request->chosen_logistic_zone_id)->first();
            # todo::[for eCommerce] handle exceptions for standard & express
            $orderGroup->total_shipping_cost                = $logisticZone->standard_delivery_charge;

            // to convert input price to base price
            if (Session::has('currency_code')) {
                $currency_code = Session::get('currency_code', Config::get('app.currency_code'));
            } else {
                $currency_code = env('DEFAULT_CURRENCY');
            }
            $currentCurrency = Currency::where('code', $currency_code)->first();

            $orderGroup->total_tips_amount                  = $request->tips / $currentCurrency->rate; // convert to base price;

            $orderGroup->grand_total_amount                 = $orderGroup->sub_total_amount + $orderGroup->total_tax_amount + $orderGroup->total_shipping_cost + $orderGroup->total_tips_amount - $orderGroup->total_coupon_discount_amount;


            if ($request->payment_method == "wallet") {
                $balance = (float) $user->user_balance;

                if ($balance < $orderGroup->grand_total_amount) {
                    flash(localize("Votre solde de portefeuille est bas"))->error();
                    return back();
                }
            }
            $orderGroup->save();

            # order -> todo::[update version] make array for each vendor, create order in loop
            $order = new Order;
            $order->order_group_id  = $orderGroup->id;
            $order->shop_id         = $carts[0]->product_variation->product->shop_id;
            $order->user_id         = $userId;
            $order->location_id     = session('stock_location_id');
            if (getCoupon() != '') {
                $order->applied_coupon_code         = getCoupon();
                $order->coupon_discount_amount      = $orderGroup->total_coupon_discount_amount; // todo::[update version] calculate for each vendors
            }
            $order->total_admin_earnings            = $orderGroup->grand_total_amount;
            $order->logistic_id                     = $logisticZone->logistic_id;
            $order->logistic_name                   = optional($logisticZone->logistic)->name;
            $order->shipping_delivery_type          = $request->shipping_delivery_type;

            if ($request->shipping_delivery_type == getScheduledDeliveryType()) {
                $timeSlot = ScheduledDeliveryTimeList::where('id', $request->timeslot)->first(['id', 'timeline']);
                $timeSlot->scheduled_date = $request->scheduled_date;
                $order->scheduled_delivery_info = json_encode($timeSlot);
            }

            $order->shipping_cost                   = $orderGroup->total_shipping_cost; // todo::[update version] calculate for each vendors
            $order->tips_amount                     = $orderGroup->total_tips_amount; // todo::[update version] calculate for each vendors

            $order->save();

            # order items
            $total_points = 0;


            $apiEndpoint = env('API_CATEGORIES_URL');
            $sCodeTiers = auth()->user()->CODETIERS;

            if($sCodeTiers == null){
                $UserAddress = UserAddress::where('user_id', auth()->user()->id)->firstOrFail();
            
            $Client = [
                "Adresse" => $UserAddress->address ?? '',
                "CodePostal" => auth()->user()->postal_code ?? '',
                "Ville" => $UserAddress->city->name ?? '',
                "Mobile "=> $request->phone ?? '',
                 "RIB" => 0, 
                 "Société" => auth()->user()->name,
                  "NTVA" => auth()->user()->NTVA ?? '000000',
                  "IDSecteurs"=> 0,
                   "IDCHAUFFEURS"=> 0, 
                   "EMail" => auth()->user()->email, 
                   "Portable"=>$request->phone ?? '',
            ];
            $ClientApiPost = Http::post("$apiEndpoint/Client", $Client);
            if ($ClientApiPost->successful()) {
                $ClientApiPostData = $ClientApiPost->json();
               
                $sCodeTiers = $ClientApiPostData['CODETIERS'];
                //dd(auth()->user());
                $user = User::find(auth()->user()->id);
                $user->codetiers =$sCodeTiers;
            $user->save();
              // Wait until codetiers is updated or maximum retries reached
              $maxRetries = 100; // Adjust as needed
              $retryCount = 0;
              while ($user->codetiers == null && $retryCount < $maxRetries) {
                  sleep(1); // Adjust the sleep time as needed
                  $user = User::find(auth()->user()->id);
                  $retryCount++;
              }
            }
            
            
            }
            $sCodeTiers =$user->codetiers;
            if($sCodeTiers != null){   

            $rTotalHT = $orderGroup->sub_total_amount;
            $RtotalTTC = $orderGroup->grand_total_amount;
            
            $userId = auth()->user()->id; // Ensure $userId is correctly set
            $stockLocationId = session('stock_location_id'); // Ensure stock_location_id is correctly set
            
            $cartsQuery = Cart::with('product_variation.product')
                ->where('user_id', $userId)
                ->where('location_id', $stockLocationId);
            
            $carts = $cartsQuery->get();
            
            
            $mainOrderApiData = [
                "LASource" => $request->payment_method . ',' . $request->shipping_delivery_type . ',' . $logisticZone->logistic->name . ',' . $orderGroup->payment_status . ',' . $orderGroup->payment_details,
            ];
            
            // Document API request
            $mainOrderApiResponse = Http::post("$apiEndpoint/Document/$sCodeTiers/$rTotalHT/$RtotalTTC", $mainOrderApiData);
            
            if ($mainOrderApiResponse->successful()) {
                $mainOrderResponseData = $mainOrderApiResponse->json();
                $idDocument = $mainOrderResponseData['IDDocument'];
            
            
                foreach ($carts as $cart) {

                    $orderItem = new OrderItem;
                    $orderItem->order_id = $order->id;
                    $orderItem->product_variation_id = $cart->product_variation_id;
                    $orderItem->qty = $cart->qty;
                    $orderItem->location_id = session('stock_location_id');
                    $orderItem->unit_price = variationDiscountedPrice($cart->product_variation->product, $cart->product_price);
                    $orderItem->total_tax = variationTaxAmount($cart->product_variation->product, $cart->product_variation);
                    $orderItem->total_price = $orderItem->unit_price * $orderItem->qty;
                    $orderItem->save();
            
                    $product = $cart->product_variation->product;
                    $product->total_sale_count += $orderItem->qty;
            
                    $productId = $product->id;

                    $product = Product::find($productId);

                    if ($product) {
                        $slug = $product->slug;
                        $barcode = strpos($slug, '-') !== false ? substr($slug, 0, strpos($slug, '-')) : $slug;
                    } else {
                    
                    }
            
                    $apiLineData = [
                        "IDDocument"      => $idDocument,
                        "Référence"        => "",
                        "LibProd"          => $cart->product_variation->product->name,
                        "Quantité"         => $cart->qty,
                        "PrixVente"       => variationDiscountedPrice($cart->product_variation->product, $cart->product_price),
                        "TauxTVA"          => $cart->product_variation->product->tax_percentage,
                        "TotaleTTC"       => $orderItem->total_price, 
                        "TotaleHT"        => $cart->unit_price * $cart->qty,
                        "totaletva"       => $cart->total_tax,
                        "IDProduit"        => $cart->product_variation->product->id,
                        "dateheuresaisie" => now()->format('Y-m-d H:i:s'),
                    ];

                  //  dd( $apiLineData);


            
                    // LigneDocument API request
                    $apiLineResponse = Http::post("{$apiEndpoint}/LigneDocument/{$idDocument}/{$barcode}", $apiLineData);
            
                    if ($apiLineResponse->successful()) {
                       
                    } else {
                        
                    }
            
                    
            
                    # reward points
                    if (getSetting('enable_reward_points') == 1) {
                        $orderItem->reward_points = $product->reward_points * $orderItem->qty;
                        $total_points += $orderItem->reward_points;
                    }
            
                    // minus stock qty
                    try {
                        $productVariationStock = $cart->product_variation->product_variation_stock;
                        $productVariationStock->stock_qty -= $orderItem->qty;
                        $productVariationStock->save();
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                    $product->stock_qty -= $orderItem->qty;
                    $product->save();
            
                    # category sales count
                    if ($product->categories()->count() > 0) {
                        foreach ($product->categories as $category) {
                            $category->total_sale_count += $orderItem->qty;
                            $category->save();
                        }
                    }
            
                    $cart->delete();
                }
                $apiLineData1 = [
                    "IDDocument"      => $idDocument,
                    "Référence"        => "",
                    "LibProd"          => "Transport Marchandise "  . $logisticZone->logistic->name . "\n" . $order->scheduled_delivery_info ,
                    "Quantité"         => 1,
                    "PrixVente"       => $orderGroup->total_shipping_cost,
                    
                    "dateheuresaisie" => now()->format('Y-m-d H:i:s'),
                ];

              


        
                // LigneDocument API request
                $apiLineResponse = Http::post("{$apiEndpoint}/LigneDocument/{$idDocument}/{$barcode}", $apiLineData1);

                $apiLineData2 = [
                    "IDDocument"      => $idDocument,
                    "Référence"        => "",
                    "LibProd" => "Moy Paiement  " . $request->payment_method . "\n" . "NonPayé" ,

                    "Quantité"         => 1,
                    
                    
                    "dateheuresaisie" => now()->format('Y-m-d H:i:s'),
                ];

              //  dd( $apiLineData);


        
                // LigneDocument API request
                $apiLineResponse = Http::post("{$apiEndpoint}/LigneDocument/{$idDocument}/{$barcode}", $apiLineData2);


            } else {
                dd('API request for Document failed', $mainOrderApiResponse->status(), $mainOrderApiResponse->body());
            }
            
            

            # reward points
            if (getSetting('enable_reward_points') == 1) {
                $reward = new RewardPoint;
                $reward->user_id = $userId;
                $reward->order_group_id = $orderGroup->id;
                $reward->total_points = $total_points;
                $reward->status = "pending";
                $reward->save();
            }

            $order->reward_points = $total_points;
            $order->save();

            # increase coupon usage
            if (getCoupon() != '' && $orderGroup->total_coupon_discount_amount > 0) {
                $coupon = Coupon::where('code', getCoupon())->first();
                $coupon->total_usage_count += 1;
                $coupon->save();

                # coupon usage by user
                $couponUsageByUser = CouponUsage::where('user_id', auth()->user()->id)->where('coupon_code', $coupon->code)->first();
                if (!is_null($couponUsageByUser)) {
                    $couponUsageByUser->usage_count += 1;
                } else {
                    $couponUsageByUser = new CouponUsage;
                    $couponUsageByUser->usage_count = 1;
                    $couponUsageByUser->coupon_code = getCoupon();
                    $couponUsageByUser->user_id = $userId;
                }
                $couponUsageByUser->save();
                removeCoupon();
            }

            # payment gateway integration & redirection

            $orderGroup->payment_method = $request->payment_method;
            $orderGroup->save();




            if ($request->payment_method != "cod" && $request->payment_method != "wallet") {
                $request->session()->put('payment_type', 'order_payment');
                $request->session()->put('order_code', $orderGroup->order_code);
                $request->session()->put('payment_method', $request->payment_method);
                $request->session()->put('Document_id', $idDocument);
                

                # init payment
                $payment = new PaymentsController;
                return $payment->initPayment();
            } else if ($request->payment_method == "wallet") {
                $orderGroup->payment_status = paidPaymentStatus();
                $orderGroup->order->update(['payment_status' => paidPaymentStatus()]); 
                $orderGroup->save();

                $user->user_balance -= $orderGroup->grand_total_amount;
                $user->save();

                flash(localize('Votre commande a été passée avec succès.'))->success();
                return redirect()->route('checkout.success', $orderGroup->order_code);
            } else {
                flash(localize('Votre commande a été passée avec succès.'))->success();
                return redirect()->route('checkout.success', $orderGroup->order_code);
            }
        }
        else{

            flash(localize('Veuillez reéssayer '))->error();
            return redirect()->back();
            //Console.log(auth()->user()->CODETIERS);
        }
        }

        flash(localize('Votre panier est vide'))->error();
        return back();
    }

    # order successful
    public function success($code)
    {
        $orderGroup = OrderGroup::where('user_id', auth()->user()->id)->where('order_code', $code)->first();
        $user = auth()->user();
    
        // todo:: change this from here
        try {
            Notification::send($user, new OrderPlacedNotification($orderGroup->order));
        } catch (\Exception $e) {
        }
        return getView('pages.checkout.invoice', ['orderGroup' => $orderGroup]);
    }


    # order invoice
    public function invoice($code)
    {
        $orderGroup = OrderGroup::where('user_id', auth()->user()->id)->where('order_code', $code)->first();
        $user = auth()->user();
        return getView('pages.checkout.invoice', ['orderGroup' => $orderGroup]);
    }

    # update payment status
    public function updatePayments($payment_details)
    {
        $apiEndpoint = env('API_CATEGORIES_URL');
        $orderGroup = OrderGroup::where('order_code', session('order_code'))->first();
        $payment_method = session('payment_method');
      $document_id  = session('Document_id');
        $orderGroup->payment_status = paidPaymentStatus();
        $orderGroup->order->update(['payment_status' => paidPaymentStatus()]); # for multi-vendor loop through each orders & update

        $orderGroup->payment_method = $payment_method;
        $orderGroup->payment_details = $payment_details;
        $orderGroup->save();
        $apiLineData3 = [
            "IDDocument"      => $document_id,
            "Référence"        => "",
            "LibProd" => "Moy Paiement  " . $payment_method . "\n" . "Payé",
            "Quantité"         => 1,
            "dateheuresaisie" => now()->format('Y-m-d H:i:s'),
        ];
        
        $apiLineResponse = Http::post("{$apiEndpoint}/LigneDocument/{$document_id}/{}", $apiLineData3);
        
        if ($apiLineResponse->successful()) {
            // Successfully updated the status to "payé"
        } else {
            // Handle the case where the API call fails
        }
        clearOrderSession();
        flash(localize('Votre commande a été passée avec succès.'))->success();
        return redirect()->route('checkout.success', $orderGroup->order_code);
    }
}
