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

    public function getInstitutionColumn(): string
    {
        return $this->institutionColumn ?? 'ins_code';
    }
}
