<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2002-2008 (original work) Public Research Centre Henri Tudor & University of Luxembourg (under the project TAO & TAO2);
 *               2008-2010 (update and modification) Deutsche Institut für Internationale Pädagogische Forschung (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 *               2013-2014 (update and modification) Open Assessment Technologies SA
 */

namespace oat\taoTestCenter\scripts\update;

use oat\generis\model\OntologyRdfs;
use oat\oatbox\event\EventManager;
use oat\tao\model\accessControl\func\AccessRule;
use oat\tao\model\accessControl\func\AclProxy;
use oat\tao\model\event\UserRemovedEvent;
use oat\tao\model\import\service\ImportMapper;
use oat\tao\model\user\import\UserCsvImporterFactory;
use oat\taoProctoring\model\authorization\TestTakerAuthorizationInterface;
use oat\taoProctoring\model\ProctorServiceInterface;
use oat\taoTestCenter\controller\Import;
use oat\taoTestCenter\model\breadcrumbs\OverriddenDeliverySelectionService;
use oat\taoTestCenter\model\breadcrumbs\OverriddenMonitorService;
use oat\taoTestCenter\model\breadcrumbs\OverriddenReportingService;
use oat\taoTestCenter\model\EligibilityService;
use oat\taoTestCenter\model\import\RdsTestCenterImportService;
use oat\taoTestCenter\model\import\TestCenterAdminCsvImporter;
use oat\taoTestCenter\model\import\TestCenterCsvImporterFactory;
use oat\taoTestCenter\model\proctoring\TestCenterAuthorizationService;
use oat\taoTestCenter\model\proctoring\TestCenterProctorService;
use oat\tao\scripts\update\OntologyUpdater;
use oat\taoTestCenter\model\TestCenterService;
use oat\taoTestTaker\models\events\TestTakerRemovedEvent;
use oat\tao\model\ClientLibConfigRegistry;

/**
 *
 * @access public
 * @package taoGroups
 */
class Updater extends \common_ext_ExtensionUpdater
{

    /**
     * (non-PHPdoc)
     * @see common_ext_ExtensionUpdater::update()
     * @throws \common_Exception
     */
    public function update($initialVersion)
    {
        if ($this->isBetween('0.0.1', '0.3.0')) {
            throw new \common_Exception('Upgrade unavailable');
        }

        $this->skip('0.3.0', '2.0.2');

        if ($this->isVersion('2.0.2')) {
            OntologyUpdater::syncModels();
            $this->setVersion('2.0.3');
        }

        $this->skip('2.0.3', '2.0.4');

        if ($this->isVersion('2.0.4')) {
            $delegator = $this->getServiceManager()->get(ProctorServiceInterface::SERVICE_ID);
            $delegator->registerHandler(new TestCenterProctorService());
            $this->getServiceManager()->register(ProctorServiceInterface::SERVICE_ID, $delegator);
            $this->setVersion('2.1.0');
        }

        if ($this->isVersion('2.1.0')) {
            $delegator = $this->getServiceManager()->get(TestTakerAuthorizationInterface::SERVICE_ID);
            $delegator->registerHandler(new TestCenterAuthorizationService());
            $this->getServiceManager()->register(TestTakerAuthorizationInterface::SERVICE_ID, $delegator);

            $this->setVersion('3.0.0');
        }

        $this->skip('3.0.0', '3.0.1');

        if ($this->isVersion('3.0.1')) {

            $eventManager = $this->getServiceManager()->get(EventManager::SERVICE_ID);
            $eventManager->attach(UserRemovedEvent::EVENT_NAME, [EligibilityService::SERVICE_ID, 'deletedTestTaker']);
            $eventManager->attach(TestTakerRemovedEvent::EVENT_NAME, [EligibilityService::SERVICE_ID, 'deletedTestTaker']);
            $this->getServiceManager()->register(EventManager::SERVICE_ID, $eventManager);

            $this->setVersion('3.1.0');
        }

        $this->skip('3.1.0', '3.1.2');

        if ($this->isVersion('3.1.2')) {
            $this->getServiceManager()->register(
                OverriddenDeliverySelectionService::SERVICE_ID,
                new OverriddenDeliverySelectionService()
            );

            $this->setVersion('3.2.0');
        }
        $this->skip('3.2.0', '3.2.3');

        if ($this->isVersion('3.2.3')) {
            ClientLibConfigRegistry::getRegistry()->register('taoTestCenter/component/eligibilityEditor', [
                'deliveriesOrder' => 'http://www.w3.org/2000/01/rdf-schema#label',
                'deliveriesOrderdir' => 'asc',
            ]);

            $this->setVersion('3.3.0');
        }

        $this->skip('3.3.0', '3.8.0');

        if ($this->isVersion('3.8.0')) {
            /** @var UserCsvImporterFactory $importerFactory */
            $importerFactory = $this->getServiceManager()->get(UserCsvImporterFactory::SERVICE_ID);
            $typeOptions = $importerFactory->getOption(UserCsvImporterFactory::OPTION_MAPPERS);
            $typeOptions[TestCenterAdminCsvImporter::USER_IMPORTER_TYPE] = array(
                UserCsvImporterFactory::OPTION_MAPPERS_IMPORTER => new TestCenterAdminCsvImporter()
            );
            $importerFactory->setOption(UserCsvImporterFactory::OPTION_MAPPERS, $typeOptions);
            $this->getServiceManager()->register(UserCsvImporterFactory::SERVICE_ID, $importerFactory);

            $this->setVersion('3.9.0');
        }

        if ($this->isVersion('3.9.0')) {
            AclProxy::applyRule(new AccessRule(AccessRule::GRANT, TestCenterService::ROLE_TESTCENTER_MANAGER, Import::class));
            AclProxy::applyRule(new AccessRule(AccessRule::GRANT, TestCenterService::ROLE_TESTCENTER_ADMINISTRATOR, Import::class));

            $service = new TestCenterCsvImporterFactory(array(
                TestCenterCsvImporterFactory::OPTION_DEFAULT_SCHEMA => array(
                    ImportMapper::OPTION_SCHEMA_MANDATORY => [
                        'label' => OntologyRdfs::RDFS_LABEL,
                    ],
                    ImportMapper::OPTION_SCHEMA_OPTIONAL => []
                )
            ));
            $typeOptions = [];
            $typeOptions['default'] = array(
                TestCenterCsvImporterFactory::OPTION_MAPPERS_IMPORTER => new RdsTestCenterImportService()
            );
            $service->setOption(TestCenterCsvImporterFactory::OPTION_MAPPERS, $typeOptions);

            $this->getServiceManager()->register(TestCenterCsvImporterFactory::SERVICE_ID, $service);

            $this->setVersion('3.10.0');
        }

        $this->skip('3.10.0', '3.10.1');
    }
}
