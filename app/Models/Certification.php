<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

class Certification extends ApiModel
{
    protected $table = 'certification';
    public $timestamps = true;
    protected $auditModel = true;

    protected $guarded = [];

    protected $rules = [
        'title' => 'required|string',
        'on_sl_report' => 'sometimes|boolean',
        'sl_title' => 'sometimes|required_if:on_sl_report,1|string|nullable',
        'is_lifetime_certification' => 'sometimes|boolean',
    ];

    protected $appends = [
        'total_people'
    ];

    /**
     * Find all certifications for the given criteria
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $sql = self::query();
        $onShiftReport = $query['on_sl_report'] ?? null;
        if ($onShiftReport !== null) {
            $sql->where('on_sl_report', $onShiftReport);
        }

        return $sql->get();
    }

    /**
     * Get the total number of people who have this certification.
     * @return int
     */

    public function getTotalPeopleAttribute() : int
    {
        return PersonCertification::where('certification_id', $this->id)->count();
    }
}
