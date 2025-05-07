<?php

/**
 * Base Api Controller that support using dynamic model as modelName
 */

namespace StieTotalWin\DynaModel\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use CodeIgniter\RESTful\ResourceController;

class ApiController extends ResourceController
{
	protected $modelName;

	/**
	 * @return void
	 */
	public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);

		if (! class_exists($this->modelName))
		{
			$model = \StieTotalWin\DynaModel\DB::table($this->modelName);

			$this->setModel($model);
		}
	}
}
