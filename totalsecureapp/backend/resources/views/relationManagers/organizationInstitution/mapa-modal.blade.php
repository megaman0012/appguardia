<div id="leaflet-map" style="width:100%; height:300px;"></div>
<button type="button" id="gps-btn" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded">Usar GPS</button>

<script>
    function initMap() {
        let latInput = document.querySelector("input[name='im_lat']");
        let lngInput = document.querySelector("input[name='im_lng']");

        let lat = latInput && latInput.value ? parseFloat(latInput.value) : -12.0464; // valor por defecto
        let lng = lngInput && lngInput.value ? parseFloat(lngInput.value) : -77.0428;

        let map = L.map("leaflet-map").setView([lat, lng], 17);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(map);

        let marker = L.marker([lat, lng], { draggable: true }).addTo(map);

        function updateInputs(lat, lng) {
            if(latInput){ latInput.value = lat; latInput.dispatchEvent(new Event("input")); }
            if(lngInput){ lngInput.value = lng; lngInput.dispatchEvent(new Event("input")); }
        }

        marker.on("dragend", function() {
            let pos = marker.getLatLng();
            updateInputs(pos.lat, pos.lng);
        });

        document.getElementById("gps-btn").addEventListener("click", function(){
            if(navigator.geolocation){
                navigator.geolocation.getCurrentPosition(function(pos){
                    let lat = pos.coords.latitude;
                    let lng = pos.coords.longitude;
                    updateInputs(lat,lng);
                    map.setView([lat,lng],17);
                    marker.setLatLng([lat,lng]);
                }, function(err){
                    alert("No se pudo obtener la ubicación: " + err.message);
                });
            } else {
                alert("GPS no soportado en este navegador.");
            }
        });
    }

    // Cargar Leaflet si no existe
    if(!window.L){
        let link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = "https://unpkg.com/leaflet/dist/leaflet.css";
        document.head.appendChild(link);

        let script = document.createElement("script");
        script.src = "https://unpkg.com/leaflet/dist/leaflet.js";
        script.onload = initMap;
        document.body.appendChild(script);
    } else {
        initMap();
    }
</script>
