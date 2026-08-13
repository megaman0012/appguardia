<!--<style>

    .max-wi-full{
        width: 50% !important;
    }

    .buton-close{
        font-size: 50px !important;
        background-color: white;
        border: 2px solid black;
        border-radius: 40px;
        padding: 2px 5px 6px 5px;
    }

</style>
<div x-data="{ open: false }" class="relative">
    <img src="{{ $getRecord()->imagen_url }}" alt="Imagen" class="h-9 w-9 rounded-full cursor-pointer" @click="open = true" >

    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" >
        <div class="bg-white p-4 rounded shadow-lg max-w-full max-h-full relative">
            <button @click="open = false" class="buton-close absolute top-2 right-2 text-gray-600 hover:text-black text-xl" >
                &times;
            </button>
            <img src="{{ $getRecord()->imagen_url }}" alt="Imagen ampliada" class="max-wi-full max-w-full max-h-[80vh] rounded" >
        </div>
    </div>
</div>-->

<style>
    .popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(0, 0, 0, 0.4); /* opcional: fondo oscuro */
        z-index: 9999;
    }

    .popup-image-wrapper {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10000;
    }

    .popup-image-wrapper img {
        display: block;
        max-width: 30vw;
        height: auto;
    }

    .popup-close {
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(245, 158, 11, 0.97);
        color: black;
        border: 2px solid black;
        border-radius: 50%;
        font-size: 24px;
        width: 36px;
        height: 36px;
        text-align: center;
        line-height: 30px;
        cursor: pointer;
        z-index: 10001;
    }
</style>

<div x-data="{ open: false }">
    <!-- Imagen miniatura -->
    @if ($getRecord()->imagen_url)
    <img
        src="{{ $getRecord()->imagen_url }}"
        alt=" "
        style="height: 35px; width: 35px; border-radius: 50%; cursor: pointer; margin-left: 20px;"
        @click="open = true"
    >
    @endif

    <!-- Fondo oscuro (opcional) -->
    <template x-if="open">
        <div class="popup-overlay" @click.self="open = false">
            <!-- Botón cerrar -->


            <!-- Imagen centrada -->
            <div class="popup-image-wrapper">
                <span class="popup-close" @click="open = false">&times;</span>
                <img src="{{ $getRecord()->imagen_url }}" alt="Imagen ampliada">
            </div>
        </div>
    </template>
</div>
