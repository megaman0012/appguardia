
@extends('acceso::layouts.master')
@section('content')
<div  id="login-box" class="login-box visible widget-box no-border" >

	<div class="account-wall">
	<div style="text-align: center;" class="widget-body">
        <img src="{{ asset('images/logo.png') }}" alt="AdminLTE Logo" class="brand-image" style="opacity: .8;width: 59px;margin-top: -15px;margin-right: -10px;">
        <span class="brand-text font-weight-light" style="font-size: 40px;">Dt360 Core</span>
		<form class="form-signin"  id="loginForm" name="loginForm" method="POST" action="../procesar_cambiopass" autocomplete="off">
			<input type="hidden" id="_token" name="_token" value="{{{ csrf_token() }}}" />
			<p>Ingrese Nueva Contrase&ntilde;a</p>
		  	<div style="margin-bottom: 20px" class="input-group has-feedback">
                <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                <input id="password" type="password" class="form-control" name="password" placeholder="Nueva Contraseña" autocomplete="off">
            </div>
            <p>Repetir Nueva Contrase&ntilde;a</p>
			<div style="margin-bottom: 20px" class="input-group has-feedback">
                <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                <input id="password2" type="password" class="form-control" name="password2" placeholder="Repetir Nueva Contraseña" autocomplete="off" >
                <input type="hidden" name="user_id" value="{{ $user_id }}" />
            </div>
			<div class="control-group">
				<div class="controls">
					<button style="font-size: 15px;" class="btn btn-lg btn-warning btn-block" id="btnSubmit" name="login" type="submit"><i class="fa fa-sign-out"></i>&nbsp;<b>Confirmar Cambio</b></button>
				</div>
				<a style="font-size: 15px; margin-top: 10px;" title="Consultar" id="btnConsultar"   class='btn btn-lg btn-outline-dark btn-block' href="{!! url('/').'/acceso/login' !!}" ><i class="fa fa-reply-all"></i>&nbsp;<b>Regresar Login</b> </a>
			</div>
			<br/>
			<div id="res">
                @if(Session::has('message-error'))
                    <div class="alert alert-danger" style="padding: 0.45rem 1.25rem !important; background-color: #ff5969 !important; border-color: #f08c96 !important;">
                        R : {{ Session::get('message-error') }}
                    </div>
                @endif
            </div>

		</form>
	</div>
	</div>
</div><!-- Login box -->
@endsection
