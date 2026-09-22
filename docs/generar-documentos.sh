#!/usr/bin/env bash
# =============================================================================
# generar-documentos.sh — Regenera HTML, PDF y DOCX a partir de un .md
#
# Los entregables (INFORME_AUDITORIA, Analisis_Seguridad, los manuales) viven en
# cuatro formatos. El .md es la fuente; los otros tres se derivan. Si se edita el
# .md y no se corre esto, el cliente lee la version vieja: ya paso una vez, con un
# informe que seguia marcando SEC-00 como critico abierto cuatro dias despues de
# haberlo cerrado.
#
# ⚠️ **El PDF sale del dompdf del propio proyecto, no de pandoc.** En este
# servidor no hay ningun motor de PDF instalado (`pdflatex`, `weasyprint`,
# `wkhtmltopdf`), asi que `pandoc -o x.pdf` falla. dompdf ya es dependencia del
# backend para los QR, y corre dentro del contenedor.
#
# ⚠️ **Por eso hay un directorio de paso.** El compose monta `backend/:/var/www`,
# no la raiz del monorepo, asi que el contenedor NO ve `INFORME_AUDITORIA.md`.
# El HTML se copia a `backend/storage/app/docgen/`, se convierte alli, y el PDF
# se trae de vuelta.
#
# ⚠️ **Y por eso se entra con `-u 1000`**, igual que en `artisan.sh`: entrar como
# root deja archivos de root en `storage/` y el siguiente worker de php-fpm no
# puede tocarlos. Eso tumbo el panel con un 500 el 2026-09-17.
#
# La tipografia es DejaVu porque es la unica que dompdf trae con cobertura de
# acentos. Lo que NO tiene son los emoji de severidad: comprobado con
# `fc-list ':charset=1F534'`, a DejaVu Sans le faltan 🔴 🟠 🟡 🔵 🟢, ✅ y 🚨, y en el PDF
# salian como un hueco (18 en el informe de auditoria). Si tiene ⚠ ⚪ ● y ✔.
#
# Por eso la rama del PDF sustituye cada emoji por un circulo U+25CF del color
# que toca. **El .html y el .docx conservan el emoji original**, que ahi si se ve:
# la sustitucion se aplica a la copia que entra a dompdf, no al entregable.
# Aun asi cada fila de severidad lleva ademas la palabra (CRITICO, ALTO, MEDIO),
# para que el documento no dependa del color ni de la vista.
#
# Uso:  ./docs/generar-documentos.sh                    # los dos de auditoria
#       ./docs/generar-documentos.sh docs/03_DESARROLLO/API.md
# =============================================================================

set -euo pipefail

RAIZ="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$RAIZ/totalsecureapp/backend"
PASO_HOST="$BACKEND/storage/app/docgen"
PASO_CONT="/var/www/storage/app/docgen"

# Sin argumentos, los dos entregables de auditoria.
DOCUMENTOS=("$@")
if [[ ${#DOCUMENTOS[@]} -eq 0 ]]; then
  DOCUMENTOS=("INFORME_AUDITORIA.md" "docs/05_SEGURIDAD/Analisis_Seguridad.md")
fi

command -v pandoc >/dev/null || { echo "Falta pandoc: dnf install pandoc" >&2; exit 1; }
docker compose --project-directory "$BACKEND" ps --status running --services 2>/dev/null \
  | grep -qx backend || { echo "El contenedor 'backend' no esta corriendo." >&2; exit 1; }

CSS='
body{font-family:DejaVu Sans,sans-serif;font-size:10pt;line-height:1.45;color:#222}
h1{font-size:19pt;border-bottom:2px solid #C0172C;padding-bottom:6px;color:#C0172C}
h2{font-size:14pt;margin-top:20px;border-bottom:1px solid #ddd;padding-bottom:3px}
h3{font-size:11.5pt;margin-top:14px}
table{border-collapse:collapse;width:100%;margin:10px 0;font-size:8.5pt}
th,td{border:1px solid #bbb;padding:5px 6px;text-align:left;vertical-align:top}
th{background:#f2f2f2}
code{background:#f4f4f4;padding:1px 3px;font-family:DejaVu Sans Mono,monospace;font-size:8.5pt}
pre{background:#f7f7f7;padding:8px;border-left:3px solid #C0172C}
blockquote{border-left:3px solid #C0172C;margin-left:0;padding-left:12px;color:#444;background:#fcf6f7}
'

mkdir -p "$PASO_HOST"
trap 'rm -rf "$PASO_HOST"' EXIT

for MD in "${DOCUMENTOS[@]}"; do
  RUTA="$RAIZ/$MD"
  [[ -f "$RUTA" ]] || { echo "No existe: $MD" >&2; exit 1; }

  BASE="${RUTA%.md}"
  NOMBRE="$(basename "$BASE")"
  echo "== $MD"

  # HTML: fragmento de pandoc con la hoja de estilo por delante.
  { printf '<html><head><meta charset="utf-8"><style>%s</style></head><body>' "$CSS"
    pandoc "$RUTA" --from=gfm --to=html
    printf '</body></html>'
  } > "$BASE.html"
  echo "   html  $(du -h "$BASE.html" | cut -f1)"

  # DOCX: pandoc directo, es el unico de los tres que sabe hacerlo solo.
  pandoc "$RUTA" --from=gfm --to=docx --output="$BASE.docx"
  echo "   docx  $(du -h "$BASE.docx" | cut -f1)"

  # PDF: por el dompdf del backend, dentro del contenedor.
  # Antes, los emoji que DejaVu no tiene -> circulo coloreado que si tiene.
  sed -e 's|🔴|<span style="color:#C0172C">●</span>|g' \
      -e 's|🟠|<span style="color:#D2691E">●</span>|g' \
      -e 's|🟡|<span style="color:#B8860B">●</span>|g' \
      -e 's|🔵|<span style="color:#1A6FB0">●</span>|g' \
      -e 's|🟢|<span style="color:#1F8A3B">●</span>|g' \
      -e 's|✅|<span style="color:#1F8A3B">✔</span>|g' \
      -e 's|🚨|<span style="color:#C0172C">⚠</span>|g' \
      -e 's|️||g' \
      "$BASE.html" > "$PASO_HOST/$NOMBRE.html"
  docker compose --project-directory "$BACKEND" exec -T -u 1000 backend \
    php -d memory_limit=512M -r '
      require "/var/www/vendor/autoload.php";
      $nombre = $argv[1];
      $dir    = "'"$PASO_CONT"'";
      $dompdf = new Dompdf\Dompdf(new Dompdf\Options([
          "isRemoteEnabled"      => false,
          "isHtml5ParserEnabled" => true,
          "defaultFont"          => "DejaVu Sans",
      ]));
      $dompdf->loadHtml(file_get_contents("$dir/$nombre.html"), "UTF-8");
      $dompdf->setPaper("A4", "portrait");
      $dompdf->render();
      file_put_contents("$dir/$nombre.pdf", $dompdf->output());
    ' -- "$NOMBRE"
  mv "$PASO_HOST/$NOMBRE.pdf" "$BASE.pdf"
  echo "   pdf   $(du -h "$BASE.pdf" | cut -f1)  ($(pdfinfo "$BASE.pdf" 2>/dev/null | awk '/^Pages/{print $2" paginas"}'))"
done

echo
echo "Listo. Revisar que el PDF abra antes de commitear."
