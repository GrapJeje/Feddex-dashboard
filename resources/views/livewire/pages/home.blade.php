<div>
    @section('title', 'Feddex Dashboard')
    @php
        $devivery = new \App\Models\Delivery();
        var_dump($devivery->items);
    @endphp
</div>
