<footer>

    @php
        $currentYear = date('Y');
        $startYear = '2024';
        $year = 'Error';
    @endphp
        <!--------------
            Footer
        ---------------->
    @vite(['resources/sass/partials/footer.scss', 'resources/js/app.js'])


    <div class="footer-links">
        <a href="https://grapjeje.nl/">Home</a>
        <a href="https://web.grapjeje.nl/">Web</a>
    </div>

    @php
        if ($currentYear == $startYear) {
            $year = "$startYear";
        } else {
            $year = "$startYear - $currentYear";
        }
    @endphp

    <p> © Jason {{ $year }} </p>

</footer>
