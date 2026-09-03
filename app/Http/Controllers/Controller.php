<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Resim/media çekme işlemleri için tenant ID'yi döndürür
     * OMR_MAIN_TENANT varsa onu, yoksa OMR_TENANT_ID'yi kullanır
     * 
     * @return string|null
     */
    protected function getMainTenant(): ?string
    {
        return OmrConfig::tenantForSharedContent();
    }
}
