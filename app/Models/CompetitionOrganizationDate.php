<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionOrganizationDate extends Base
{
    use HasFactory;

    protected $table = "competition_organization_date";

    protected $fillable = [
        "competition_partner_id",
        "competition_date",
        "created_by_userid",
        "created_at"
    ];
}
