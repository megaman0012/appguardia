<table id="{{ $id }}" class="{{ $class }}">
    <thead>
        <tr>
            {{ $thead }}
        </tr>
    </thead>
    <tbody>
        @if(isset($tbody))
            {{ $tbody }}
        @endif
    </tbody>
</table>
