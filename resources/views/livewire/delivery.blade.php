<div>
    <div class="packages-grid">
        @php
            $defaultAmount = 5;
            $currentAmount = min(request('amount', $defaultAmount), count($items));
            $showMore = $currentAmount < count($items);
            $showLess = $currentAmount > $defaultAmount;
        @endphp

        @foreach(array_slice($items, 0, $currentAmount) as $item)
            <div class="package-card">
                <div class="card-header">
                    <h3>Pakketdetails #{{ $item->tracking_code ?? 'Geen tracking code' }}</h3>
                    <span class="priority-badge {{ strtolower($item->priority) }}">{{ $item->priority }}</span>
                </div>
                <div class="card-body">
                    <div class="package-info">
                        <div class="info-row">
                            <span class="label">Gewicht:</span>
                            <span class="value">{{ $item->weight }} kg</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Formaat:</span>
                            <span class="value">{{ $item->format }} ({{ $item->dimensions }})</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Status:</span>
                            <span class="value status-{{ strtolower(str_replace(' ', '-', $item->tracking_status)) }}">
                                {{ $item->tracking_status }}
                                @if($item->days_in_transit > 0)
                                    @if($item->days_in_transit == 1)
                                        1 dag onderweg)
                                    @else
                                    {{ $item->days_in_transit }} dagen onderweg
                                    @endif
                                @endif
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">Bezorging:</span>
                            <span class="value">{{ $item->delivery_day }} - {{ $item->delivery_time }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Fragiel:</span>
                            <span class="value">{{ $item->fragile ? 'Ja' : 'Nee' }}</span>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="address">
                            <h4>Afzender</h4>
                            <p>{{ $item->sender->street }} {{ $item->sender->house_number }}</p>
                            <p>{{ $item->sender->postal_code }} {{ $item->sender->city }}</p>
                            <p>{{ $item->sender->province }}, {{ $item->sender->country }}</p>
                        </div>

                        <div class="address">
                            <h4>Ontvanger</h4>
                            <p>{{ $item->receiver->street }} {{ $item->receiver->house_number }}</p>
                            <p>{{ $item->receiver->postal_code }} {{ $item->receiver->city }}</p>
                            <p>{{ $item->receiver->province }}, {{ $item->receiver->country }}</p>
                        </div>
                    </div>

                    <div class="delivery-info">
                        <div class="info-row">
                            <span class="label">Aangemeld:</span>
                            <span class="value">{{ $item->registration_date ?? 'Onbekend' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Verzonden:</span>
                            <span class="value">{{ $item->shipping_date ?? 'Onbekend' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Chauffeur:</span>
                            <span class="value">{{ $item->driver ?? 'Onbekend' }}
                                @if($item->delivery_van_id)
                                    (Bus #{{ $item->delivery_van_id }})
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($showMore || $showLess)
        <div class="load-more-container">
            <div>
                @if($showMore)
                    <button class="load-btn more" onclick="loadPackages(5)">Meer laden (+5)</button>
                @endif
                @if($showLess)
                    <button class="load-btn less" onclick="loadPackages(-5)">Minder laden (-5)</button>
                @endif
            </div>
            <p class="load-counter">
                @php
                    echo $currentAmount . "/" . count($items)
                @endphp
            </p>
        </div>
    @endif

    <script>
        function loadPackages(change) {
            const url = new URL(window.location.href);
            let currentAmount = parseInt(url.searchParams.get('amount')) || {{ $defaultAmount }};
            const newAmount = currentAmount + change;

            if (newAmount >= {{ $defaultAmount }} && newAmount <= {{ count($items) }}) {
                url.searchParams.set('amount', newAmount);
                window.location.href = url.toString();
            }
        }
    </script>
</div>
