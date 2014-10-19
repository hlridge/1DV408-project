<?php

namespace BoostMyAllowanceApp\View;

use BoostMyAllowanceApp\Model\Model;
use BoostMyAllowanceApp\Model\Task;
use BoostMyAllowanceApp\Model\Transaction;

class EventsView extends View {

    private static $postConfirmEventButtonNameKey = "GodkannEllerNeka";
    private static $getIsPendingKey = "onlypending";
    private $showOnlyPendingEvents;
    private $events;

    public function __construct(Model $model) {
        parent::__construct($model, "Events");

        $this->showOnlyPendingEvents = isset($_GET[self::$getIsPendingKey]) ? $_GET[self::$getIsPendingKey] : false;
        if ($this->showOnlyPendingEvents) {
            $this->events = $this->model->getPendingEvents();
        } else {
            $this->events = $this->model->getEvents();
        }
    }

    function getHtml() {
        $html = '
<div class="panel panel-info">
    <div class="panel-heading">
    <div>
        <h3 class="panel-title">' . (($this->showOnlyPendingEvents) ? 'Avvaktande u' : 'U') . 'ppgifter och överföringar&nbsp;&nbsp;&nbsp;' .
                (($this->showOnlyPendingEvents) ?
                '<a href="?page=' . EventsView::getPageName() . '&onlypending=0"><input type="button" class="btn btn-info btn-sm" value="Visa alla" /></a>' :
                '<a href="?page=' . EventsView::getPageName() . '&onlypending=1"><button type="button" class="btn btn-info btn-sm">Visa bara avvaktande</button></a>') .'
        </h3>
        </div>

    </div>
    <div class="panel-body">
        <form action="' . $_SERVER['PHP_SELF'] . '" method="post">
            <div class="list-group">' .
                $this->getHtmlForEventLines() . '
            </div>
        </form>
    </div>
</div>';

        return parent::getSurroundingHtml($html);
    }

    private function getHtmlForEventLines() {
        $html = "";

        foreach($this->events as $event) {
            $html .= '
            <a href="#" class="list-group-item">
                <h4 class="list-group-item-heading">' .
                    (($event->getIsPending()) ? '
                    <input type="submit"
                        class="btn btn-danger pull-right"
                        name="' . self::$postConfirmEventButtonNameKey . '"
                        value="Neka" /> ' : '') . '
                    <input type="submit"
                        class="btn btn-info pull-right"
                        name="' . self::$postConfirmEventButtonNameKey . '"
                        value="Redigera" />' .
                    (($event->getIsPending()) ? '
                        <input type="submit"
                        class="btn btn-success pull-right"
                        name="' . self::$postConfirmEventButtonNameKey . '"
                        value="Godkänn" />' : '') .
                        $event->getTitle() . '</h4>
                <p><span class="label label-info">' .
                $this->model->getChildsName($event->getAdminUserEntityId())
                . '</span>'.
                (($event->getClassName() == Task::getClassName()) ?
                    '<span class="label label-info">
                    Giltig: 2014-02-24 20:30 - 2014-02-25 20:30</span>' : '') .
                '<span class="label label-info">' .
                ($event->getIsRequested() ? 'Utförd: ' . $this->formatTimestamp($event->getTimeOfRequest()) : 'Ej utförd')
                . '</span></p>
                <p class="list-group-item-text">
                    <span class="label label-' . (($event->getIsConfirmed()) ? 'success' : (($event->getIsDenied()) ? 'danger' : (($event->getIsPending()) ? 'warning' : 'info'))) . ' pull-left">' .
                        $event->getStatusText()
                    . '</span>&nbsp;<span>
                    ' . $event->getDescription() . '</span>
                </p>
            </a>
            ';
        }
        unset($event);

        return $html;
    }
}
