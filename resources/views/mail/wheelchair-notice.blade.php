<!DOCTYPE html>
<html lang="de-CH">
    <body style="font-family: Helvetica, Arial, sans-serif; color: #181818; line-height: 1.5;">
        @if ($cancelled)
            <p>Eine Bestellung mit Rollstuhlplatz wurde ganz oder teilweise storniert. Bereits zugeteilte Rollstuhlplätze sind wieder gesperrt.</p>
        @else
            <p>Jemand hat beim Ticketkauf einen Rollstuhlplatz angekreuzt. Diese Bestellung bekommt keine Mail zur Platzwahl und wird nicht automatisch zugeteilt. Bitte meldet euch bei der Person.</p>
        @endif

        <ul>
            <li>Name: {{ $order->first_name }} {{ $order->last_name }}</li>
            <li>E-Mail: <a href="mailto:{{ $order->email }}">{{ $order->email }}</a></li>
            <li>Bestellung: {{ $order->short_id }}</li>
            <li>Aktive Tickets in der Bestellung: {{ $tickets }}</li>
            <li>Rollstuhlplatz angekreuzt für: {{ $wheelchairAttendees->join(', ') }}</li>
        </ul>
    </body>
</html>
