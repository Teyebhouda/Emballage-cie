@extends('backend.layouts.master')

@section('title')
    {{ localize(' Paramètres de commandes') }} {{ getSetting('title_separator') }} {{ getSetting('system_title') }}
@endsection

@section('contents')
    <section class="tt-section pt-4">
        <div class="container">
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card tt-page-header">
                        <div class="card-body d-lg-flex align-items-center justify-content-lg-between">
                            <div class="tt-page-title">
                                <h2 class="h5 mb-lg-0">{{ localize(' Paramètres de commandes') }}</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 pb-650">
                <!--left sidebar-->
                <div class="col-xl-9 order-2 order-md-2 order-lg-2 order-xl-1">
                    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data"
                        class="mb-4">
                        @csrf

                        <!--order settings-->
                        <div class="card mb-4" id="section-1">
                            <div class="card-body">
                                <h5 class="mb-4">{{ localize('Informations sur la commande') }}</h5>

                                <div class="mb-3">
                                    <label for="enable_scheduled_order"
                                        class="form-label">{{ localize('Activer la commande planifiée') }}</label>
                                    <input type="hidden" name="types[]" value="enable_scheduled_order">
                                    <select id="enable_scheduled_order" class="form-control text-uppercase select2"
                                        name="enable_scheduled_order" data-toggle="select2">
                                        <option value="1"
                                            {{ getSetting('enable_scheduled_order') == '1' ? 'selected' : '' }}>
                                            {{ localize('Activé') }}</option>
                                        <option value="0"
                                            {{ getSetting('enable_scheduled_order') == '0' ? 'selected' : '' }}>
                                            {{ localize('Désactivé') }}</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="allowed_order_days"
                                        class="form-label">{{ localize('Jours de commande planifiée') }}</label>
                                    <input type="hidden" name="types[]" value="allowed_order_days">
                                    <input type="number" id="allowed_order_days" name="allowed_order_days"
                                        class="form-control" min="1" value="{{ getSetting('allowed_order_days') }}">
                                </div>

                                <div class="mb-3">
                                    <label for="order_code_prefix"
                                        class="form-label">{{ localize('Préfixe du code de commande') }}</label>
                                    <input type="hidden" name="types[]" value="order_code_prefix">
                                    <input type="text" id="order_code_prefix" name="order_code_prefix"
                                        class="form-control" placeholder="{{ localize('#Grostore-') }}"
                                        value="{{ getSetting('order_code_prefix') }}">
                                </div>
                                <div class="mb-3">
                                    <label for="order_code_start"
                                        class="form-label">{{ localize('Le code de commande commence à partir de') }}</label>
                                    <input type="hidden" name="types[]" value="order_code_start">
                                    <input type="number" min="1" id="order_code_start" name="order_code_start"
                                        class="form-control" placeholder="{{ localize('1001') }}"
                                        value="{{ getSetting('order_code_start') }}">
                                </div>
                                <div class="mb-3">
                                    <label for="invoice_thanksgiving"
                                        class="form-label">{{ localize('Message de remerciement pour le Bon de Commande') }}</label>
                                    <input type="hidden" name="types[]" value="invoice_thanksgiving">
                                    <textarea rows="4" id="invoice_thanksgiving" name="invoice_thanksgiving" class="form-control"
                                        placeholder="{{ localize('Saisissez votre message de remerciement pour le Bon de Commande') }}">{{ getSetting('invoice_thanksgiving') }}</textarea>
                                </div>
                            </div>
                        </div>
                        <!--order settings-->


                        <div class="mb-3">
                            <button class="btn btn-primary" type="submit">
                                <i data-feather="save" class="me-1"></i> {{ localize('Enregistrer Configuration') }}
                            </button>
                        </div>
                    </form>

                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="card mb-4" id="section-2">

                                <div class="card-body pb-2">
                                    <h5>{{ localize('Liste des créneaux horaires planifiés') }}</h5>
                                </div>
                                <table class="table tt-footable" data-use-parent-width="true">
                                    <thead>
                                        <tr>
                                            <th class="text-center" width="7%">{{ localize('S/L') }}</th>
                                            <th>{{ localize('Créneau horaire') }}</th>
                                            <th>{{ localize('Ordre de tri') }}</th>
                                            <th data-breakpoints="xs sm" class="text-end">
                                                {{ localize('Action') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        @foreach ($slots as $key => $slot)
                                            <tr>
                                                <td class="text-center align-middle">
                                                    {{ $key + 1 }}
                                                </td>

                                                <td class="align-middle">
                                                    <h6 class="fs-sm mb-0">
                                                        {{ $slot->timeline }}</h6>
                                                </td>

                                                <td class="align-middle">
                                                    {{ $slot->sorting_order }}
                                                </td>

                                                <td class="text-end align-middle">
                                                    <div class="dropdown tt-tb-dropdown">
                                                        <button type="button" class="btn p-0" data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                            <i data-feather="more-vertical"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end shadow">

                                                            <a class="dropdown-item"
                                                                href="{{ route('admin.timeslot.edit', ['id' => $slot->id, 'lang_key' => env('DEFAULT_LANGUAGE')]) }}&localize">
                                                                <i data-feather="edit-3"
                                                                    class="me-2"></i>{{ localize('Modifier') }}
                                                            </a>

                                                            <a href="#" class="dropdown-item confirm-delete"
                                                                data-href="{{ route('admin.timeslot.delete', $slot->id) }}"
                                                                title="{{ localize('Supprimer') }}">
                                                                <i data-feather="trash-2" class="me-2"></i>
                                                                {{ localize('Supprimer') }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('admin.timeslot.store') }}" method="POST" enctype="multipart/form-data"
                        class="mt-4" id="section-3">
                        @csrf

                        <!--timeslot-->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="mb-4">{{ localize('Ajouter nouveau créneau horaire') }}</h5>
                                <div class="mb-3">
                                    <label for="timeline" class="form-label">{{ localize('Créneau horaire') }}</label>
                                    <input type="text" id="timeline" name="timeline" class="form-control"
                                        placeholder="{{ localize('8am - 9am') }}" required>
                                </div>

                                <div class="mb-3">
                                    <label for="sorting_order" class="form-label">{{ localize('Ordre de tri') }}</label>
                                    <input type="number" min="0" value="0" id="sorting_order"
                                        name="sorting_order" class="form-control">
                                    <small>{{ localize('Les créneaux horaires avec un ordre de tri inférieur seront affichés en premier') }}</small>
                                </div>

                            </div>
                        </div>
                        <!--timeslot-->


                        <div class="mb-3">
                            <button class="btn btn-primary" type="submit">
                                <i data-feather="save" class="me-1"></i> {{ localize('Sauvegarder') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!--right sidebar-->
                <div class="col-xl-3 order-1 order-md-1 order-lg-1 order-xl-2">
                    <div class="card tt-sticky-sidebar">
                        <div class="card-body">
                            <h5 class="mb-4">{{ localize(' Configurer les paramètres de commande') }}</h5>
                            <div class="tt-vertical-step">
                                <ul class="list-unstyled">
                                    <li>
                                        <a href="#section-1" class="active">{{ localize('Informations sur la commande') }}</a>
                                    </li>

                                    <li>
                                        <a href="#section-2">{{ localize(' Liste des créneaux horaires') }}</a>
                                    </li>

                                    <li>
                                        <a href="#section-3">{{ localize('Ajouter Nouveau créneau horaire') }}</a>
                                    </li>

                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script>
        "use strict";

        // runs when the document is ready --> for media files
        $(document).ready(function() {
            //  
        });
    </script>
@endsection
