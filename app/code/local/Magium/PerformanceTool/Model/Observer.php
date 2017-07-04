<?php

class Magium_PerformanceTool_Model_Observer
{

    const CONFIG_ENABLED = 'magium/performance_tool/enabled';
    const CONFIG_TRIGGER = 'magium/performance_tool/trigger';

    private $events = [];
    private $enabled = true;

    public function observe(Varien_Event_Observer $observer)
    {
        if (!$this->enabled) return ;

        $enabled = Mage::getStoreConfigFlag(self::CONFIG_ENABLED);
        if (!$enabled) {
            $this->enabled = false;
            return;
        } else {
            $triggerHeader = Mage::getStoreConfig(self::CONFIG_TRIGGER);
            if ($triggerHeader) {
                $value = Mage::app()->getRequest()->getHeader($triggerHeader);
                if (empty($value)) {
                    $this->enabled = false;
                    return;
                }
            }
        }

        $eventName = $observer->getEvent()->getName();

        $subjects = [];
        foreach ($observer->getData() as $key => $value) {
            if ($key == 'event') continue;
            if (is_object($value)) {
                if ($key == 'transport' && $value instanceof Varien_Object) continue;
                 $data = ['type' => get_class($value)];
                 if ($value instanceof Mage_Core_Model_Abstract) {
                     $data['id'] = $value->getId();
                 } else if ($value instanceof Mage_Core_Model_Resource_Db_Collection_Abstract) {
                     $data['select'] = (string)$value->getSelect();
                 } else if ($value instanceof Mage_Core_Block_Template) {
                     $data['template'] = $value->getTemplate();
                 }
                 if ($value instanceof Mage_Core_Block_Abstract) {
                     $data['block-name'] = $value->getNameInLayout();
                 }
            } else  {
                $data = [
                    'type' => gettype($value)
                ];
            }
            if ($data) {
                $subjects[$key] = $data;
            }
        }

        if (!$this->events) {
            register_shutdown_function([$this, 'shutdown']);
            $this->events[] = [
                'url' => Mage::app()->getRequest()->getPathInfo(),
                'query' => Mage::app()->getRequest()->getQuery(),
                'scheme' => Mage::app()->getRequest()->getScheme(),
                'method' => Mage::app()->getRequest()->getMethod(),
                'params' => Mage::app()->getRequest()->getParams()
            ];
        }

        $this->events[] = [
            'timestamp' => microtime(true),
            'event' => $observer->getEvent()->getName(),
            'subjects' => $subjects
        ];

    }

    public function observeRoute(Varien_Event_Observer $observer)
    {
        if ($this->enabled) {
            $this->events[] = [
                'event' => 'controller_action_predispatch',
                'module' => Mage::app()->getRequest()->getModuleName(),
                'controller' => Mage::app()->getRequest()->getControllerName(),
                'action' => Mage::app()->getRequest()->getActionName(),
                'params' => Mage::app()->getRequest()->getParams(),
            ];
        }
    }

    public function shutdown()
    {
        if (count($this->events) == 1) return; // Not in the frontend

        $reportDirectory = Mage::app()->getConfig()->getOptions()->getVarDir() . DIRECTORY_SEPARATOR . 'magium_performance_tool';
        if (!file_exists($reportDirectory)) {
            mkdir($reportDirectory);
        }
        $payload = json_encode([
            'type'         => 'performance',
            'timestamp'     => microtime(true),
            'hostname'      => gethostname(),
            'payload'       => $this->events]);
        $uniqFilename = sha1(uniqid());

        $file = $reportDirectory . DIRECTORY_SEPARATOR . $uniqFilename;

        file_put_contents($file, $payload);
    }

}
