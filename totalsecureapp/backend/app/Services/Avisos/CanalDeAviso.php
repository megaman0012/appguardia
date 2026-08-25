<?php

namespace App\Services\Avisos;

/**
 * Por dónde le llega un aviso a una persona.
 *
 * Existe como interfaz por una razón concreta: hoy el único canal que funciona
 * es la notificación push de la app, y ni siquiera llega a un teléfono real
 * hasta que se configure Firebase. Cuando se decida agregar WhatsApp o SMS
 * —que es lo que la gente realmente lee, pero cuesta por mensaje y exige una
 * cuenta aprobada— se agrega una clase que implemente esto y se la registra en
 * `config/avisos.php`. Ni el servicio de vacantes ni el panel cambian.
 */
interface CanalDeAviso
{
    /** Nombre corto para la bitácora: 'push', 'whatsapp', 'sms'. */
    public function nombre(): string;

    /**
     * Manda el aviso. Devuelve si salió, nunca lanza: un aviso que no se pudo
     * entregar no puede hacer fallar la operación que lo originó.
     */
    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): bool;
}
