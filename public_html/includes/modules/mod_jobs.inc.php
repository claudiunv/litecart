<?php

  class mod_jobs extends module {
    public $data;
    public $cheapest = '';
    public $items = array();
    public $destination = array();

    public function __construct() {
      $this->load('job');
    }

    public function process($modules=null, $force=false) {

      if (empty($this->modules)) return;

      if (empty($modules)) $modules = array_keys($this->modules);
      if (!is_array($modules)) $modules = array($modules);

      foreach ($modules as $module_id) {
        if (!in_array($module_id, array_keys($this->modules))) {
          trigger_error($module_id .' is not a valid module id', E_USER_WARNING);
          continue;
        }

        ob_start();

        $timestamp = microtime(true);

        $this->modules[$module_id]->process($force);

        $log = ob_get_clean();

        if (!empty($log)) {
          $log =  '##'.str_repeat('#', strlen($title=$module_id .' executed at '. date('Y-m-d H:i:s'))).'##' . PHP_EOL
                . '# '.$title.' #' . PHP_EOL
                . '##'.str_repeat('#', strlen($title)).'##' . PHP_EOL
                . $log . PHP_EOL
                . '##'.str_repeat('#', strlen($duration='Completed in '. round(microtime(true) - $timestamp, 3).' s')).'##' . PHP_EOL
                . '# '.$duration.' #' . PHP_EOL
                . '##'.str_repeat('#', strlen($duration)).'##' . PHP_EOL;

          echo $log;
        }
      }
    }

    public function run($method_name, $module_id) {
      if (method_exists($this->modules[$module_id], $method_name)) {
        return call_user_func_array(array($this->modules[$module_id], $method_name), array_slice(func_get_args(), 2));
      }
    }
  }
