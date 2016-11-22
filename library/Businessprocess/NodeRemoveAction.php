<?php

namespace Icinga\Module\Businessprocess;

/**
 * NodeRemoveAction
 *
 * Tracks removed nodes
 *
 * @package Icinga\Module\Businessprocess
 */
class NodeRemoveAction extends NodeAction
{
    /**
     * @inheritdoc
     */
    public function appliesTo(BusinessProcess $bp)
    {
        return $bp->hasNode($this->getNodeName());
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BusinessProcess $bp)
    {
        $bp->removeNode($this->getNodeName());
    }
}
