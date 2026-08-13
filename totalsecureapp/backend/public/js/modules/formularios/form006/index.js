var dataurl = urlbase + "/formularios/epicrisis.datatable";

$(document).ready(function() {
    ajax.setup();
    dtable.basic('#dtTable');
    $('#frmQry').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        ajax.send( formData, process_getbydoc, urlbase+'/formularios/epicrisis.getbydoc', 'POST' )
    });
    $(document).on('click', '.seepi', function (e) {
        e.preventDefault();
        window.open( urlbase+'/formularios/epicrisis.document/'+$(this).data('token'), '_blank' )
    });
});

var process_getbydoc = function(data){
    if(data.errors){
        mensaje_error('', data.errors)
        dtable.basic('#dtTable');
    }else{
        dtable.basic('#dtTable', data.content);
    }
}
