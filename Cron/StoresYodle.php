<?php

/**
 * Created by PhpStorm.
 * User: ovidiu
 * Date: 17.05.2016
 * Time: 11:09
 */
namespace Acme\DataBundle\Model\Cron;

use Acme\DataBundle\Model\Constants\StoresStatus;
use Acme\DataBundle\Model\Utility\Logs;
use Acme\DataBundle\Model\Utility\Notification;
use Acme\DataBundle\Entity\Yodle;


class StoresYodle extends Cron implements CronInterface
{
    public function add($csvFile, $logFile, $params = array()) {
        $finalData = $this->getCsvImportData($csvFile, $logFile);
        try {

            for($i=0;$i<count($finalData);$i++) {

                preg_match('#\(([^\)]+)\)#', $finalData[$i]['client_name'], $matches);
                $storeId = str_replace('#', '', $matches[1]);

                //check if we have store id in database
                $checkStore = $this->em->getRepository('AcmeDataBundle:Stores')->findOneByStoreId($storeId);

                if($checkStore && $checkStore->getLocationStatus() != StoresStatus::CLOSED ) {
                    $checkYodle = $this->em->getRepository('AcmeDataBundle:Yodle')->findOneByStoreID($checkStore->getId());
                    if($checkYodle){
                        $yodle = $checkYodle;
                    } else {
                        $yodle = new Yodle();
                        $yodle->setAssetsUUID($finalData[$i]['essentials_widget_id']);
                        $yodle->setStoreID($checkStore->getId());
                        $yodle->setReviewsUUID($finalData[$i]['rateabiz_widget_id']);
                        $yodle->setYotrackUUID($finalData[$i]['client_id']);
                        $yodle->setRateABiz($finalData[$i]['rate-a-biz']);
                    }
                    $this->em->persist($yodle);
                    $this->em->flush();
                }
            }

            //delete redis cache
            $cache = $this->container->get('cacheManagementBundle.redis')->initiateCache();
            //find keys
            $keys = $cache->find('*stores*');
            //delete cache
            if(!empty($keys)) {
                for($i=0;$i<count($keys);$i++) {
                    $cache->delete($keys[$i]);
                }
            }

            return $this->notification = new Notification(true);

        } catch(\Exception $e) {
            Logs::write($this->logFile, $e->getMessage());
            return $this->notification = new Notification(false , $e->getMessage());
        }
    }
}