<?php

namespace App\Traits;

trait BelongsToInstitution
{
    public function scopeForInstitution($query, $institutionCode)
    {
        return $query->where(
            $this->getTable() . '.' . $this->getInstitutionColumn(),
            $institutionCode
        );
    }

    /**
     * Acota la consulta a un conjunto de instituciones.
     *
     * Lo usa la API del portal cliente (Fase 8), donde un token puede tener
     * varias instituciones asignadas. Con un arreglo vacio no devuelve nada:
     * "sin instituciones" tiene que significar "sin datos", nunca "todos".
     */
    public function scopeForInstitutions($query, array $institutionCodes)
    {
        return $query->whereIn(
            $this->getTable() . '.' . $this->getInstitutionColumn(),
            $institutionCodes
        );
    }

    public function getInstitutionColumn(): string
    {
        return $this->institutionColumn ?? 'ins_code';
    }
}
