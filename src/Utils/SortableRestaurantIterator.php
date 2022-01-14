<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\TimingRegistry;
use Carbon\Carbon;

class SortableRestaurantIterator extends \ArrayIterator
{
    public function __construct($array = [], TimingRegistry $timingRegistry)
    {
        $this->timingRegistry = $timingRegistry;

        $featured = array_filter($array, function (LocalBusiness $lb) {
            return $lb->isFeatured();
        });

        $notFeatured = array_filter($array, function (LocalBusiness $lb) {
            return !$lb->isFeatured();
        });

        usort($featured,    [$this, 'nextSlotComparator']);
        usort($notFeatured, [$this, 'nextSlotComparator']);

        parent::__construct(array_merge($featured, $notFeatured));
    }

    public function nextSlotComparator(LocalBusiness $a, LocalBusiness $b)
    {
        $aTimeInfo = $this->timingRegistry->getForObject($a);
        $bTimeInfo = $this->timingRegistry->getForObject($b);

        if (empty($aTimeInfo) && empty($bTimeInfo)) {

            return 0;
        }

        if (empty($aTimeInfo)) {

            return 1;
        }

        if (empty($bTimeInfo)) {

            return -1;
        }

        $aStart = new \DateTime($aTimeInfo['range'][0]);
        $bStart = new \DateTime($bTimeInfo['range'][0]);

        if ($aStart === $bStart) {

            return 0;
        }

        return $aStart < $bStart ? -1 : 1;
    }
}
