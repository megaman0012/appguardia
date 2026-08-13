<!DOCTYPE html>
<html>
    <head>
        <style>
            @page {
                margin: 100px 0;
            }

            .tagBar{
                background-color: #C0172CFF;
                width: 100%;
                height: 50px;
            }

            .tagInfo{
                background-color: #2B2B2BFF;
                width: 100%;
                text-align: center;
            }

            header {
                position: fixed;
                width: 100%;
                top: -100px;
                left: 0;
                right: 0;
                height: 60px;
                text-align: center;
            }

            footer {
                position: fixed;
                bottom: 95px;
                left: 0;
                right: 0;
                height: 40px;
                text-align: center;
                font-size: 12px;
            }
        </style>
        <title></title>
    </head>
    <body>

        <header>
            <div class="tagBar" ></div>

            <div class="tagInfo" style="padding: 50px 0 30px 0; margin-top: -1px;">
                <span style="display: inline-block; vertical-align: middle;">
                    <img src="{{ asset('/images/logo.png') }}" alt="Logo" style="width: 80px; vertical-align: middle;">
                </span>
                <span style="display: inline-block; vertical-align: middle; font-size: 35px; margin-left: 5px; font-weight: bold;">
                    <span style="color: #AFAFAFFF; font-family: Arial, Helvetica, sans-serif;">Total</span> <span style="color: #C0172CFF; font-family: Arial, Helvetica, sans-serif;">Secure</span>
                </span>
            </div>
        </header>

        <footer>
            <div class="tagInfo" style="padding: 60px 0 60px 0;">
                <span style="font-size: 30px; font-weight: bold;">
                    <span style="color: white; font-family: Arial, Helvetica, sans-serif;">{{ $marc->im_descripcion }}</span>
                    <span style="color: #C0172CFF; font-family: Arial, Helvetica, sans-serif;">{{ $marc->im_tipo }}</span>
                </span>
                <br>
                <!--<span style="font-size: 25px; font-weight: bold;">
                    <span style="color: #C0172CFF; font-family: Arial, Helvetica, sans-serif;">Institucion</span>
                </span>-->
                <span style="font-size: 25px; font-weight: bold;">
                    <span style="color: #AFAFAFFF; font-family: Arial, Helvetica, sans-serif;">{{ $inst->ins_descripcion }}</span>
                </span>




            </div>
            <div class="tagBar" style="margin-top: -1px;" ></div>
        </footer>

        <main>

            <table style="width: 100%; margin-top: 180px; z-index: 3;">
                <tr>
                    <td align="center" style="width: 100%;">
                        <div class="qr-container">
                            <img style="border: 5px solid #C0172CFF; border-radius: 10px;" width="470px" src="{{ $qrcode }}" alt="Código QR">
                        </div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; z-index: 4; margin-top: 10px;">
                <tr>
                    <td align="center" style="width: 100%;">
                        <span style="font-size: 30px;font-family: Arial, Helvetica, sans-serif;"> Escanear Codigo</span>
                    </td>
                </tr>
            </table>

        </main>

    </body>
</html>
