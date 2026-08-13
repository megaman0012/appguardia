var dataurl = urlbase + "/administracion/persona.datatable";
var columnas = [
    {data: 'pt_code', name: 'pt_code'},
    {data: 'documento', name: 'documento'},
    {data: 'pt_nmb_comp', name: 'pt_nmb_comp'},
    {data: 'pt_fch_nac', name: 'pt_fch_nac'},
    {data: 'pt_pais', name: 'pt_pais'},
    {data: 'estado', name: 'estado'},
    {data: 'accion', name: 'accion'},
];

$(document).ready(function() {
    ajax.setup();
    dtable.server(dataurl, columnas, '#dtPersona');
    $('#frmNewPt').submit(function (e) {
        e.preventDefault();
        var form = $(this).serialize();
        ajax.send( form, person_store, urlbase+'/administracion/persona.store' )
    });
    $('#pt_ape1, #pt_ape2, #pt_nmb1, #pt_nmb2').on('input', function() {
        updateFullName();
    });
});

var updateFullName = function(){
    var ptApe1 = $('#pt_ape1').val();
    var ptApe2 = $('#pt_ape2').val();
    var ptNmb1 = $('#pt_nmb1').val();
    var ptNmb2 = $('#pt_nmb2').val();
    var fullName = ptApe1 + ' ' + ptApe2 + ' ' + ptNmb1 + ' ' + ptNmb2;
    $('#pt_nmb_comp').val(fullName);
}

var person_store = function(data) {
    if(data.errors){
        mensaje_error('', data.errors)
    }else{
        toastr_show(data.msg, 'Informacion');
        dtable.server_reload('#dtPersona');
        $("#mdlNewPt").modal('hide');
        $('#frmNewPt').trigger('reset');
    }
};
