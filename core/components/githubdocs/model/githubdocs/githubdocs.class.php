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
    /** @var ParsedownExtra $parse */
    protected $parse;

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
            'connectorUrl' => $assetsUrl . 'connector.php'
        ), $config);
        $this->modx->addPackage('githubdocs', $this->config['modelPath']);
    }

    /**
     * Returns GitHub API response.
     *
     * @param string $repo_owner - Repository's owner
     * @param string $repo_name - Repository's name
     * @param string $uri - Relative path to target docs folder
     * @param bool $private - Private repo flag
     * @param bool $parse - Return parsed markdown flag
     * @param bool $toc - Return parsed table of content flag
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getContents(string $repo_owner, string $repo_name, string $uri, bool $private = false, bool $parse = true, bool $toc = true)
    {
        $params = array();
        if ($private) {
            $params = array(
                'auth' => [$this->modx->getOption('githubdocs.act_username'), $this->modx->getOption('githubdocs.act_password')]
            );
        }
        try {
            $base_uri = 'https://api.github.com/repos/' . $repo_owner . '/' . $repo_name . '/contents/';
            $client = new GuzzleHttp\Client(['base_uri' => $base_uri]);
            $response = $client->request('GET', $uri, $params);
            $body = json_decode($response->getBody(), true);
            if (isset($body['content']) && $parse) {
                $body['content'] = $this->parseMarkDown($body['content']);
                if ($toc) {
                    $markupFixer = new TOC\MarkupFixer();
                    $toc = new TOC\TocGenerator();
                    $body['content'] = $markupFixer->fix($body['content']);
                    $body['toc'] = $this->cleanLinks($toc->getHtmlMenu($body['content'],2,5));
                }
            }
            return $body;
        } catch (GuzzleHttp\Exception\GuzzleException | Exception $e) {
            throw $e;
        }
    }

    /**
     * Get a GitHub tree returning a nested array of its folders and files.
     *
     * @param string $repo_owner - Repository's owner
     * @param string $repo_name - Repository's name
     * @param string $sha - Tree SHA for target folder
     * @param bool $private - Private repo flag
     * @param bool $recursive - Recursive api query flag
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getTree(string $repo_owner, string $repo_name, string $sha, bool $private = false, bool $recursive = true)
    {
        $params = array();
        $output = array();
        if ($private) {
            $params = array(
                'auth' => [$this->modx->getOption('githubdocs.act_username'), $this->modx->getOption('githubdocs.act_password')]
            );
        }
        if ($recursive) {
            $params['query'] = array(
                'recursive' => 1
            );
        }
        try {
            $base_uri = 'https://api.github.com/repos/' . $repo_owner . '/' . $repo_name . '/git/trees/';
            $client = new GuzzleHttp\Client(['base_uri' => $base_uri]);
            $response = $client->request('GET', $sha, $params);
            $body = json_decode($response->getBody(), true);

            foreach ($body['tree'] as $k => $v) {
                $path_parts = explode('/', $v['path']);
                $temp = array();
                // $p = pointer, used to add a next level
                $p = &$temp;
                // Save the name of node
                $name = array_pop($path_parts);
                $v['name'] = $name;
                foreach ($path_parts as $part) {
                    // Make level upto the last ($part same as $name)
                    $p[$part]['children'] = array();
                    $p = &$p[$part]['children'];
                }
                // Add node to array ($part same as $name)
                $p[$name] = $v;
                // Merge node array into output
                $output = array_merge_recursive($output, $temp);
            }
            return $output;
        } catch (GuzzleHttp\Exception\GuzzleException | Exception $e) {
            throw $e;
        }
    }

    /**
     * Recursively template a nested array generated from getTree().
     *
     * @param array $array - An array generated by getDirTree()
     * @param string $dir_tpl - Chunk to use as a template
     * @param string $file_tpl - Chunk to use as a template
     * @param bool $debug - Debugging flag
     * @param array $request - $_REQUEST array
     *
     * @return array
     * @throws Exception
     */
    public function templateTree(array $array, string $dir_tpl = '', string $file_tpl = '', bool $debug = false, array $request = array())
    {
        $output = array();
        $node['class'] = '';
        $base_url = $this->config['base_url'] ?? '/';
        $dir_cls = $this->config['hereDirCls'] ?? 'active';
        $file_cls = $this->config['hereFileClass'] ?? 'active';

        foreach ($array as $node) {
            //global
            $node['title'] = $this->cleanTitle($node['name']);
            $node['_links']['modx'] = $base_url . $this->cleanUrl($node['path']);
            $node['class'] = '';
            //type specific
            switch ($node['type']) {
                case 'tree':
                    if (in_array($node['name'], $request)) {
                        $node['class'] = $dir_cls;
                    }
                    $node['children'] = $this->templateTree($node['children'], $dir_tpl, $file_tpl, $debug, $request);
                    if ($dir_tpl && !$debug) {
                        $node['children'] = implode("\n", $node['children']);
                        $node = $this->modx->getChunk($dir_tpl, $node);
                    }
                    break;
                case 'blob':
                    if (in_array(str_replace('.md', '', $node['name']), $request)) {
                        $node['class'] = $file_cls;
                    }
                    if ($file_tpl && !$debug) {
                        $node = $this->modx->getChunk($file_tpl, $node);
                    }
                    break;
            }
            $output[] = $node;
        }
        return $output;
    }

    /**
     * Get directory details.
     *
     * @param string $repo_owner - Repository's owner
     * @param string $repo_name - Repository's name
     * @param string $uri - Relative path to target docs folder
     * @param bool $private - Private repo flag
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDirectory(string $repo_owner, string $repo_name, string $uri, bool $private = false)
    {
        $output = array();
        $tmp = explode('/', $uri);
        $dir = array_pop($tmp);
        $uri = implode('/', $tmp);
        $content = $this->getContents($repo_owner, $repo_name, $uri, $private, false);
        foreach ($content as $item) {
            if ($item['name'] == $dir) {
                $output = $item;
                break;
            }
        }
        return $output;
    }

    /**
     * Returns html from base64 encoded markdown.
     *
     * @param string $md - Base64 encoded string
     *
     * @return mixed
     * @throws Exception
     */
    public function parseMarkDown(string $md = null)
    {
        try {
            $parse = new ParsedownExtra();
            $html = $this->cleanLinks($parse->text(base64_decode($md)));
            return $html;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Returns url or path with corrected extension for use with CustomRequest.
     *
     * @param string $url - url or path to clean
     *
     * @return string
     * @throws Exception
     */
    public function cleanUrl(string $url)
    {
        try {
            return str_replace(array('.md'), array('.html'), $url);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Returns a clean title.
     *
     * @param string $title - url or path to clean
     *
     * @return string
     * @throws Exception
     */
    public function cleanTitle(string $title)
    {
        try {
            return preg_replace(array(
                '/^(\d+|[a-zA-Z])_/',
                '/_/',
                '/(.md)$/'
            ), array(
                '',
                ' ',
                ''
            ), $title);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Returns a parsed html string with clean internal links.
     *
     * @param string $html - string to clean
     *
     * @return string
     * @throws Exception
     */
    public function cleanLinks(string $html)
    {
        try {
            $base_url = $this->config['base_url'] ?? '/';
            $referer = $_REQUEST['q'] ?? '';
            return str_replace(array(
                'href="/',
                'href="#',
                '.md">'
            ), array(
                'href="' . $base_url,
                'href="' . $referer . '#',
                '.html">',
            ), $html);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Custom exception handler
     *
     * @param Throwable $e
     * @param string $line (optional)
     * @param bool $fatal (optional)
     *
     * @return void;
     */
    public function exceptionHandler(Throwable $e, string $line = '', bool $fatal = false)
    {
        $code = $e->getCode();
        if ($code <= 6 || $fatal) {
            $level = modX::LOG_LEVEL_ERROR;
        } else {
            $level = modX::LOG_LEVEL_INFO;
        }
        $line = $line ? ' on Line ' . $line : '';
        $this->modx->log($level, '[gitHubDocs] - ' . $e->getMessage() . $line, '', '', '', $line);
        if ($fatal) {

        }
    }
}
