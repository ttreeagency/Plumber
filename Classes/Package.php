<?php
namespace SandstormMedia\Plumber;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.Plumber".     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use \TYPO3\FLOW3\Package\Package as BasePackage;
use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * Package base class of the SandstormMedia.Plumber package.
 *
 * @FLOW3\Scope("singleton")
 */
class Package extends BasePackage {

	protected function connectToSignals(\TYPO3\FLOW3\SignalSlot\Dispatcher $dispatcher, \SandstormMedia\PhpProfiler\Profiler $profiler, \SandstormMedia\PhpProfiler\Domain\Model\ProfilingRun $run, \TYPO3\FLOW3\Core\Bootstrap $bootstrap) {
		$dispatcher->connect('TYPO3\FLOW3\Core\Booting\Sequence', 'beforeInvokeStep', function($step) use($run) {
			$run->startTimer('Boostrap Sequence: ' . $step->getIdentifier());
		});
		$dispatcher->connect('TYPO3\FLOW3\Core\Booting\Sequence', 'afterInvokeStep', function($step) use($run) {
			$run->stopTimer('Boostrap Sequence: ' . $step->getIdentifier());
		});

		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'finishedRuntimeRun', function() use($profiler, $bootstrap) {
			$plumberConfiguration = $bootstrap->getEarlyInstance('TYPO3\FLOW3\Configuration\ConfigurationManager')->getConfiguration(\TYPO3\FLOW3\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'SandstormMedia.Plumber');


			$run = $profiler->stop();
			if ($run && isset($plumberConfiguration['enableProfiling']) && $plumberConfiguration['enableProfiling'] === TRUE) {
				$profiler->save($run);
			}
		});

		$dispatcher->connect('TYPO3\FLOW3\Core\Bootstrap', 'finishedCompiletimeRun', function() use($profiler, $bootstrap) {
			$plumberConfiguration = $bootstrap->getEarlyInstance('TYPO3\FLOW3\Configuration\ConfigurationManager')->getConfiguration(\TYPO3\FLOW3\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'SandstormMedia.Plumber');

			$run = $profiler->stop();
			if ($run && isset($plumberConfiguration['enableProfiling']) && $plumberConfiguration['enableProfiling'] === TRUE) {
				$run->setOption('Context', 'COMPILE');
				$profiler->save($run);
			}
		});

		$dispatcher->connect('TYPO3\FLOW3\Mvc\Dispatcher', 'beforeControllerInvocation', function($request, $response, $controller) use($run) {
			$run->setOption('Controller Name', get_class($controller));
			$data = array(
				'Controller' => get_class($controller)
			);
			if ($request instanceof \TYPO3\FLOW3\Mvc\ActionRequest) {
				$data['Action'] = $request->getControllerActionName();
			}

			$run->startTimer('MVC: Controller Invocation', $data);
		});
		$dispatcher->connect('TYPO3\FLOW3\Mvc\Dispatcher', 'afterControllerInvocation', function() use($run) {
			$run->stopTimer('MVC: Controller Invocation');
		});
	}

	public function boot(\TYPO3\FLOW3\Core\Bootstrap $bootstrap) {
		$bootstrap->registerRequestHandler(new RequestHandler($bootstrap));
		define('XHPROF_ROOT', $this->getResourcesPath() . 'Private/PHP/xhprof-ui/');

		if (!file_exists(FLOW3_PATH_DATA . 'Logs/Profiles')) {
			mkdir(FLOW3_PATH_DATA . 'Logs/Profiles');
		}

		$profiler = \SandstormMedia\PhpProfiler\Profiler::getInstance();
		$profiler->setConfiguration('profilePath', FLOW3_PATH_DATA . 'Logs/Profiles');

		$run = $profiler->start();
		$dispatcher = $bootstrap->getSignalSlotDispatcher();
		$run->setOption('Context', $bootstrap->getContext());
		$this->connectToSignals($dispatcher, $profiler, $run, $bootstrap);
	}
}
?>