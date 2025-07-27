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

    /**
     * @acl(name=view)
     */
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
                $this->mfExtractDataPackage->downloadMfSchemesData();
                $this->mfExtractDataPackage->extractMfSchemesData();
                $this->mfExtractDataPackage->processMfSchemesData();
                if ($this->config->databasetype !== 'db' &&
                    $this->postData()['downloadnav'] != 'true'
                ) {
                    $this->mfExtractDataPackage->reIndexMfSchemesData();
                }
            }

            if ($this->postData()['downloadnav'] == 'true') {
                $this->mfExtractDataPackage->downloadMfNavsData();
                $this->mfExtractDataPackage->extractMfNavsData();
                $this->mfExtractDataPackage->processMfNavsData();
                if ($this->config->databasetype !== 'db' &&
                    $this->postData()['schemes'] != 'true'
                ) {
                    $this->mfExtractDataPackage->reIndexMfSchemesData();
                }
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
                        'method'    => 'downloadMfSchemesData',
                        'text'      => 'Download Mutual Fund Schemes Data...',
                        'remoteWeb' => true
                    ],
                    [
                        'method'    => 'extractMfSchemesData',
                        'text'      => 'Extracting Mutual Fund Schemes Data...'
                    ],
                    [
                        'method'    => 'processMfSchemesData',
                        'text'      => 'Process Extracted Mutual Fund Schemes Data...',
                        'steps'     => true
                    ]
                ]
            );

            if ($this->config->databasetype !== 'db' &&
                $this->postData()['downloadnav'] != 'true'
            ) {
                $methods = array_merge($methods,
                    [
                        [
                            'method'    => 'reIndexMfSchemesData',
                            'text'      => 'Re-indexing Mutual Fund Schemes Data...',
                        ]
                    ]
                );
            }
        }

        if ($this->postData()['downloadnav'] == 'true') {
            $methods = array_merge($methods,
                [
                    [
                        'method'    => 'downloadMfNavsData',
                        'text'      => 'Download Mutual Fund Nav Data...',
                        'remoteWeb' => true
                    ],
                    [
                        'method'    => 'extractMfNavsData',
                        'text'      => 'Extracting & Indexing Mutual Fund Nav Data...',
                        'steps'     => true
                    ],
                    [
                        'method'    => 'processMfNavsData',
                        'text'      => 'Process Extracted Mutual Fund Nav Data...',
                        'steps'     => true
                    ]
                ]
            );

            if ($this->config->databasetype !== 'db' &&
                $this->postData()['schemes'] != 'true'
            ) {
                $methods = array_merge($methods,
                    [
                        [
                            'method'    => 'reIndexMfSchemesData',
                            'text'      => 'Re-indexing Mutual Fund Nav Data...',
                        ]
                    ]
                );
            }
        }

        $this->basepackages->progress->init(null, 'mfextractdata')->registerMethods($methods);

        return true;
    }

    public function getAllNavDataAction()
    {
        $this->requestIsPost();

        $this->mfExtractDataPackage->processMfNavsData($this->postData());

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