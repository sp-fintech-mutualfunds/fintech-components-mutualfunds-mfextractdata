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
    //https://github.com/sp-fintech-mutualfunds/historical-mf-data/releases/latest/download/funds.db.zst
    //https://github.com/sp-fintech-mutualfunds/historical-mf-data/releases/latest/download/latest.db.zst
    //
    public function processAction()
    {
        $this->requestIsPost();

        if ($this->basepackages->progress->checkProgressFile('mfextractdata')) {
            $this->basepackages->progress->deleteProgressFile();
        }

        $this->registerProgressMethods();

        try {
            if ($this->postData()['schemes'] == 'false' &&
                $this->postData()['downloadnav'] == 'false'
            ) {
                $this->addResponse('Nothing selected!', 1);

                return;
            }

            if ($this->postData()['schemes'] == 'true') {
                $this->mfExtractDataPackage->downloadMfData();
                $this->mfExtractDataPackage->processMfData();
            }

            if ($this->postData()['downloadnav'] == 'true') {
                $this->mfExtractDataPackage->downloadMfData(false, true);
                $this->mfExtractDataPackage->extractMfData();
                $this->mfExtractDataPackage->processMfData(false, true);
            }

            $this->addResponse(
                $this->mfExtractDataPackage->packagesData->responseMessage,
                $this->mfExtractDataPackage->packagesData->responseCode,
                $this->mfExtractDataPackage->packagesData->responseData ?? [],
            );
        } catch (\throwable $e) {
            trace([$e]);
            $this->basepackages->progress->preCheckComplete(false);

            $this->basepackages->progress->resetProgress();

            $this->addResponse($e->getMessage(), 1);
        }
    }

    protected function registerProgressMethods()
    {
        $methods = [];

        if ($this->postData()['schemes'] == 'true') {
            $methods = array_merge($methods,
                [
                    [
                        'method'    => 'downloadMfData',
                        'text'      => 'Download Mutual Fund Schemes Data...',
                        'remoteWeb' => true
                    ],
                    [
                        'method'    => 'processMfData',
                        'text'      => 'Process Extracted Mutual Fund Schemes Data...',
                        'steps'     => true
                    ]
                ]
            );
        }

        if ($this->postData()['downloadnav'] == 'true') {
            $methods = array_merge($methods,
                [
                    [
                        'method'    => 'downloadMfData',
                        'text'      => 'Download Mutual Fund Nav Data...',
                        'remoteWeb' => true
                    ],
                    [
                        'method'    => 'extractMfData',
                        'text'      => 'Extracting & Indexing Mutual Fund Nav Data...',
                        'steps'     => true
                    ],
                    [
                        'method'    => 'processMfData',
                        'text'      => 'Process Extracted Mutual Fund Nav Data...',
                        'steps'     => true
                    ]
                ]
            );
        }

        $this->basepackages->progress->init(null, 'mfextractdata')->registerMethods($methods);

        return true;
    }

    public function getAllNavDataAction()
    {
        $this->requestIsPost();

        $this->mfExtractDataPackage->processMfData(false, false, true, $this->postData());

        $this->addResponse(
            $this->mfExtractDataPackage->packagesData->responseMessage,
            $this->mfExtractDataPackage->packagesData->responseCode,
            $this->mfExtractDataPackage->packagesData->responseData ?? []
        );
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