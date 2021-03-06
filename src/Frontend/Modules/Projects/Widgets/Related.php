<?php

namespace Frontend\Modules\Projects\Widgets;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Frontend\Core\Engine\Base\Widget as FrontendBaseWidget;
use Frontend\Core\Engine\Navigation as FrontendNavigation;
use Frontend\Modules\Projects\Engine\Model as FrontendProjectsModel;
use Frontend\Modules\Tags\Engine\Model as FrontendTagsModel;

/**
 * This is a widget with the related projects based on tags
 *
 * @author Bart Lagerweij <bart@webleads.nl>
 * @author Tim van Wolfswinkel <tim@webleads.nl>
 */
class Related extends FrontendBaseWidget
{
	/**
	 * Records to exclude
	 *
	 * @var		array
	 */
	private $exclude = array();

	/**
	 * Tags on this page
	 *
	 * @var		array
	 */
	private $tags = array();

	/**
	 * Related records
	 *
	 * @var		array
	 */
	private $related = array();

	/**
	 * Execute the extra
	 */
	public function execute()
	{
		parent::execute();
		$this->getTags();
		$this->getRelated();
		$this->loadTemplate();
		$this->parse();
	}

	/**
	 * Get related "things" based on tags
	 */
	private function getRelated()
	{
		// loop tags
		foreach($this->tags as $tag)
		{
			// fetch entries
			$items = (array) $this->get('database')->getRecords(
				'SELECT mt.module, mt.other_id
				 FROM modules_tags AS mt
				 INNER JOIN tags AS t ON t.id = mt.tag_id
				 WHERE t.language = ? AND t.tag = ? AND module=?',
				array(FRONTEND_LANGUAGE, $tag, $this->getModule())
			);

			// loop items
			foreach($items as $item)
			{
				// loop existing items
				foreach($this->related as $related)
				{
					// already exists
					if($item == $related) continue 2;
				}

				// add to list of related items
				$this->related[] = $item;
			}
		}

        $projectsIds = array();
        foreach($this->related as $id => $entry) {
            $projectsIds[] = $entry['other_id'];
        }

        $projects = FrontendProjectsModel::getProjectsByIds($projectsIds);
        if ($projects) {
            // only show 3
            $this->related = array_splice($projects, 0, 3);
            return;
        }

        $this->related = array();
	}

	/**
	 * Get tags for current "page"
	 */
	private function getTags()
	{
		// get page id
        $pageId = $this->getContainer()->get('page')->getId();

		// array of excluded records
		$this->exclude[] = array('module' => 'pages', 'other_id' => $pageId);

		// get tags for page
		$tags = (array) FrontendTagsModel::getForItem('pages', $pageId);
		foreach($tags as $tag) $this->tags = array_merge((array) $this->tags, (array) $tag['name']);

		// get page record
		$record = (array) FrontendNavigation::getPageInfo($pageId);

		// loop blocks
		foreach((array) $record['extra_blocks'] as $block)
		{
			// set module class
			$class = 'Frontend' . \SpoonFilter::toCamelCase($block['module']) . 'Model';

			if(is_callable(array($class, 'getIdForTags')))
			{
				// get record for module
				$record = FrontendTagsModel::callFromInterface($block['module'], $class, 'getIdForTags', $this->URL);

				// check if record exists
				if(!$record) continue;

				// add to excluded records
				$this->exclude[] = array('module' => $block['module'], 'other_id' => $record['id']);

				// get record's tags
				$tags = (array) FrontendTagsModel::getForItem($block['module'], $record['id']);
				foreach($tags as $tag) $this->tags = array_merge((array) $this->tags, (array) $tag['name']);
			}
		}
	}

	/**
	 * Parse
	 */
	private function parse()
	{
		// assign
		$this->tpl->assign('widgetTagsRelated', $this->related);
	}
}
