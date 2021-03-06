<?php

/*
 * This file is part of the easybook application.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Easybook\Publishers;

class BasePublisher
{
    protected $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->prepareOutputDir();
    }

    public function publishBook()
    {
        $this->loadContents();
        $this->parseContents();
        $this->decorateContents();
        $this->assembleBook();
    }

    public function prepareOutputDir()
    {
        $bookOutputDir = $this->app['publishing.dir.book'].'/Output';
        if (!file_exists($bookOutputDir)) {
            mkdir($bookOutputDir);
            // TODO: edge case -> output dir cannot be created
        }

        $editionOutputDir = $bookOutputDir.'/'.$this->app['publishing.edition'];

        if (!file_exists($editionOutputDir)) {
            mkdir($editionOutputDir);
            // TODO: edge case -> output dir cannot be created
        }

        $this->app->set('publishing.dir.output', $editionOutputDir);
    }


    public function loadContents()
    {
        // TODO: extensibility -> editions can redefine book contents (to remove or reorder items)
        foreach ($this->app->book('contents') as $contentConfig) {
            $item = $this->initializeItem($contentConfig);

            // if the element defines its own content file (usually: `chapter`, `appendix`)
            if (array_key_exists('content', $contentConfig)) {
                // TODO: extensibility -> contents could be written in several formats simultaneously
                // (e.g. Twig *and* Markdown)
                $contentFile = $this->app['publishing.dir.contents'].'/'.$contentConfig['content'];
                $item['original'] = file_get_contents($contentFile);
                $item['config']['format'] = pathinfo($contentFile, PATHINFO_EXTENSION);
            }
            else {
                $path = $this->app['publishing.dir.app_theme'].'/Contents/';
                $template = $contentConfig['element'].'.md.twig';

                // if easybook theme defines a default content for this element (`cover`, `license`, `title`)
                if (file_exists($path.'/'.$template)) {
                    $this->app->set('twig.path', $path);
                    $item['original'] = $this->app->render($template);
                    $item['config']['format']  = 'md';

                    // reset twig path variable to not interfere with further renderings
                    $this->app->set('twig.path', null);
                }
                // the element doesn't define its content and there is no easybook default content
                else {
                    $item['original'] = '';
                    $item['config']['format']  = 'md';
                }
            }

            $this->app->append('publishing.items', $item);
        }
    }

    private function initializeItem($contentConfig)
    {
        // each book element is represented by a variable of type `item`
        $item = array();

        $item['config'] = array_merge(array(
            'content' => '',  // the name of this item contents file (it's a relative path from book's `Contents/`)
            'element' => '',  // the type of this content (`chapter`, `appendix`, `toc`, `license`, ...)
            'format'  => '',  // the format in which contents are written ('md' for MArkdown)
            'number'  => '',  // the number/letter of the content (useful for `chapter`, `part` and `appendix`)
            'title'   => ''   // the title of the content defined in `config.yml` (usually only `part` defines it)
        ), $contentConfig);

        $item['content']  = '';      // transformed content of the element (HTML usually)
        $item['label']    = '';      // the label of this element ('Chapter XX', 'Appendix XX', ...)
        $item['original'] = '';      // original content as written by book author
        $item['slug']     = '';      // the slug of the title
        $item['title']    = '';      // the title of the element without any label ('Lorem ipsum dolor')
        $item['toc']      = array(); // the table of contents of this element

        if ('' != $item['config']['title']) {
            $item['title'] = $item['config']['title'];
            $item['slug']  = $this->app->get('slugger')->slugify($item['title']);
        }

        return $item;
    }
}