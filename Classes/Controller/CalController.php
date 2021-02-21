<?php
namespace Mediadreams\MdFullcalendar\Controller;

/***
 *
 * This file is part of the "FullCalendar.io for ext:Calendarize" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Christoph Daecke
 *
 ***/

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CalController
 */
class CalController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var \HDNET\Calendarize\Domain\Repository\IndexRepository
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected $indexRepository;

    /**
     * @var \Mediadreams\MdFullcalendar\Domain\Repository\CategoryRepository
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected $categoryRepository;

    /**
     * Show the calendar
     *
     * @return void
     */
    public function showAction()
    {
        if (!empty($this->settings['language'])) {
            $pageRender = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRender->addJsFooterFile('EXT:md_fullcalendar/Resources/Public/fullcalendar/packages/core/locales/'.$this->settings['language'].'.js');
        }

        if ($this->settings['category']) {
            $allCategories = $this->categoryRepository->findByParent($this->settings['category']);
            $this->view->assign('categories', $allCategories);
        }

        // pass storagePid to template in order to use it in ajax call listAction()
        $storagePid = $this->configurationManager->getContentObject()->data['pages'];
        if ($storagePid) {
            $this->view->assign('storagePid', $storagePid);
        }

        $this->view->assign('contentObject', $this->configurationManager->getContentObject()->data);
    }

    /**
     * Get list of events
     * If "type" is provided, it will return values as json object
     *
     * @return void | json
     */
    public function listAction()
    {
        $type = GeneralUtility::_GP('type');
        $storagePid = GeneralUtility::_GP('storage');

        // set end day -1 in order to get all events for selected time span
        $selectedStart = new \DateTime(GeneralUtility::_GP('start'));
        $selectedStart = $selectedStart
                            ->modify('-1 day')
                            ->setTime(00, 00, 00)
                            ->getTimestamp();

        // set end day +1 in order to get all events for selected time span
        $selectedEnd = new \DateTime(GeneralUtility::_GP('end'));
        $selectedEnd = $selectedEnd
                            ->modify('+1 day')
                            ->setTime(23, 59, 59)
                            ->getTimestamp();

        if ( !empty($storagePid) ) {
            // sanitize input
            $storagePid =  GeneralUtility::intExplode(',', $storagePid, true);
            $storagePid = implode (',', $storagePid);

            // set storagePid
            $this->configurationManager->setConfiguration(
                [
                    'persistence' => [
                        'storagePid' => $storagePid
                    ],
                ]
            );
        }

        $search = $this->indexRepository->findByTimeSlot($selectedEnd, $selectedStart);

        if ($type == 1573738558) {
            $items = [];
            foreach ($search as $el) {
                $uri = $this->uriBuilder
                    ->reset()
                    ->setTargetPageUid((int)$this->settings['pid']['defaultDetailPid'])
                    ->uriFor(
                        'detail',
                        ['index' => $el->getUid()],
                        'Calendar',
                        'calendarize',
                        'calendar'
                    );

                $uriAjax = $this->uriBuilder
                    ->reset()
                    ->setTargetPageUid((int)$this->settings['pid']['defaultDetailPid'])
                    ->setTargetPageType(1573760945)
                    ->uriFor(
                        'detail',
                        ['index' => $el->getUid()],
                        'Cal',
                        'mdfullcalendar',
                        'cal'
                    );

                if ($el->isAllDay()) {
                    $start = $el->getStartDateComplete()->format('Y-m-d');
                    $end = $el->getEndDateComplete()->modify('+1 day')->format('Y-m-d');
                } else {
                    $start = $el->getStartDateComplete()->format('c');
                    $end = $el->getEndDateComplete()->format('c');
                }

                $items[] = [
                    'id' => $el->getUid(),
                    'title' => $el->getOriginalObject()->getTitle(),
                    'abstract' => $el->getOriginalObject()->getAbstract(),
                    'description' => $el->getOriginalObject()->getDescription(),
                    'location' => $el->getOriginalObject()->getLocation(),
                    'locationLink' => $el->getOriginalObject()->getLocationLink(),
                    'organizer' => $el->getOriginalObject()->getOrganizer(),
                    'organizerLink' => $el->getOriginalObject()->getOrganizerLink(),
                    'start' => $start,
                    'end' => $end,
                    'allDay' => $el->isAllDay(),
                    'className' => 'cal-item'.$this->getCssClasses($el->getOriginalObject()->getCategories()),
                    'url' => $uri,
                    'uriAjax' => $uriAjax,
                ];
            }

            return json_encode($items);
            exit;
        } else {
            $this->view->assign('index', $search);
        }
    }

    /**
     * Get one event
     *
     * @param \HDNET\Calendarize\Domain\Model\Index $index
     * @return void
     */
    public function detailAction(\HDNET\Calendarize\Domain\Model\Index $index)
    {
        $this->view->assign('index', $index);
    }

    /**
     * This function returns a string with all CSS classes of an item
     *
     * @param $categories The ObjectStorage with the categories
     * @return string
     */
    private function getCssClasses($categories) {
        $cssClasses = '';

        foreach ($categories as $category) {
            $cssClasses .= ' category'.$category->getUid();
        }

        return $cssClasses;
    }
}
