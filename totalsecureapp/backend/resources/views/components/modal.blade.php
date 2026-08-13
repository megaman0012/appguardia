<div class="modal fade {{ $class }}" id="{{ $id }}" style="display: none;" aria-hidden="true">
    <div class="modal-dialog" style="max-width:{{ $width }};">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ $title }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                {{ $body }}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                {{ $footer }}
            </div>
        </div>
    </div>
</div>
