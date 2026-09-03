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
     * Tüm içerik isteklerinde tek TENANT_ID değerini kullanır.
     * 
     * @return string|null
     */
    protected function getMainTenant(): ?string
    {
        return OmrConfig::tenantForSharedContent();
    }
}
