<div id="{{ $id }}" class="card {{ $class }} card-tabs">

    @if(isset($header))
        <div class="card-header">
            {{ $header }}
        </div>
    @endif

    <div class="card-body">
        {{ $body }}
    </div>
</div>
