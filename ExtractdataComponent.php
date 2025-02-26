<?php

namespace Apps\Fintech\Components\Mf\Extractdata;

use Apps\Fintech\Packages\Mf\Extractdata\MfExtractdata;
use System\Base\BaseComponent;

class ExtractdataComponent extends BaseComponent
{
    protected $mfExtractDataPackage;

    public function initialize()
    {
        $this->mfExtractDataPackage = $this->usePackage(MfExtractdata::class);

        $this->setModuleSettings(true);

        $this->setModuleSettingsData([
                'apis' => $this->mfExtractDataPackage->getAvailableApis(true, false),
                'apiClients' => $this->mfExtractDataPackage->getAvailableApis(false, false)
            ]
        );
    }

    public function viewAction()
    {
        $this->view->apis = $this->mfExtractDataPackage->getAvailableApis(false, false);
    }

    //https://api.kuvera.in/mf/api/v5/fund_amcs.json - All amcs
    //https://api.kuvera.in/mf/api/v4/fund_schemes/list.json - All schemes
    //https://github.com/captn3m0/historical-mf-data/releases/latest/download/funds.db.zst
    //
    public function processAction()
    {
        $this->requestIsPost();

        if ($this->basepackages->progress->checkProgressFile()) {
            $this->basepackages->progress->deleteProgressFile();
        }

        try {
            $success = false;

            $this->mfExtractDataPackage->downloadMfData($this->postData());
            $this->mfExtractDataPackage->extractMfData($this->postData());
            $this->mfExtractDataPackage->processMfData($this->postData());

            $this->addResponse(
                $this->mfExtractDataPackage->packagesData->responseMessage,
                $this->mfExtractDataPackage->packagesData->responseCode
            );
        } catch (\throwable $e) {
            $this->basepackages->progress->preCheckComplete(false);

            $this->basepackages->progress->resetProgress();

            $this->addResponse($e->getMessage(), 1);
        }
    }

    protected function registerProgressMethods()
    {
        $methods = [];

        $methods = array_merge($methods,
            [
                [
                    'method'    => 'downloadMfData',
                    'text'      => 'Download Mutual Fund Data...',
                    'remoteWeb' => true
                ],
                [
                    'method'    => 'extractMfData',
                    'text'      => 'Extracting Mutual Fund Data...',
                    'steps'     => true
                ],
                [
                    'method'    => 'processMfData',
                    'text'      => 'Process Extracted Mutual Fund Data...',
                    'steps'     => true
                ]
            ]
        );

        $this->basepackages->progress->registerMethods($methods);

        return true;
    }

    public function syncAction()
    {
        $this->requestIsPost();

        $this->mfExtractDataPackage->sync($this->postData());

        $this->addResponse(
            $this->mfExtractDataPackage->packagesData->responseMessage,
            $this->mfExtractDataPackage->packagesData->responseCode
        );
    }
}