<?php
//composer autoloader
require_once dirname(dirname(__DIR__)) . '/vendors/autoload.php';
use GuzzleHttp\Client;

/**
 * The gitHubDocs service class.
 *
 * @package gitHubDocs
 */
class GitHubDocs
{
    /** @var modX $modx */
    public $modx;
    public $config = array();

    function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;
        $basePath = $this->modx->getOption('githubdocs.core_path', $config, $this->modx->getOption('core_path') . 'components/githubdocs/');
        $assetsUrl = $this->modx->getOption('githubdocs.assets_url', $config, $this->modx->getOption('assets_url') . 'components/githubdocs/');
        $this->modx->lexicon->load('githubdocs:default');
        $this->config = array_merge(array(
            'basePath' => $basePath,
            'corePath' => $basePath,
            'vendorPath' => $basePath . 'vendors/',
            'modelPath' => $basePath . 'model/',
            'processorsPath' => $basePath . 'processors/',
            'templatesPath' => $basePath . 'templates/',
            'chunksPath' => $basePath . 'elements/chunks/',
            'jsUrl' => $assetsUrl . 'mgr/js/',
            'cssUrl' => $assetsUrl . 'mgr/css/',
            'assetsUrl' => $assetsUrl,
            'connectorUrl' => $assetsUrl . 'connector.php',
        ), $config);
        $this->modx->addPackage('githubdocs', $this->config['modelPath']);
    }

    public function makeMenu(string $base_uri, bool $private_flag = false) {
        //http://docs.guzzlephp.org/en/stable/quickstart.html

    }
}
