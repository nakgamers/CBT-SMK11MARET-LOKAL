<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /** @var list<string> */
    protected $helpers = ['form', 'url', 'cbt'];

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);
    }

    /** Siswa yang sedang login (null bila belum). */
    protected function siswa(): ?array
    {
        $id = session()->get('student_id');

        return $id ? model(\App\Models\StudentModel::class)->find($id) : null;
    }
}
