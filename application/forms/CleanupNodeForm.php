<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;
use ipl\Sql\Connection as IcingaDbConnection;

class CleanupNodeForm extends QuickForm
{
    /** @var MonitoringBackend|IcingaDbConnection */
    protected $backend;

    /** @var BpConfig */
    protected $bp;

    /** @var BpNode */
    protected $parentNode;

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        $view = $this->getView();
        $this->addHtml(
            '<h2>' . $view->escape(
                $this->translate('Cleanup missing nodes')
            ) . '</h2>'
        );

        $this->addElement('checkbox', 'cleanup_all', [
            'class'         => 'autosubmit',
            'label'         => $this->translate('Cleanup all missing nodes'),
            'description'   => $this->translate('Remove all missing nodes from config')
        ]);

        if ($this->getSentValue('cleanup_all') !== '1') {
            $this->addElement('multiselect', 'nodes', [
                    'label'        => $this->translate('Select nodes to cleanup'),
                    'required'     => true,
                    'size'         => 8,
                    'style'        => 'width: 25em',
                    'multiOptions' => $this->bp->getMissingChildren()
            ]);
        }
    }

    /**
     * @param MonitoringBackend|IcingaDbConnection $backend
     * @return $this
     */
    public function setBackend($backend)
    {
        $this->backend = $backend;
        return $this;
    }

    /**
     * @param BpConfig $process
     * @return $this
     */
    public function setProcess(BpConfig $process)
    {
        $this->bp = $process;
        $this->setBackend($process->getBackend());
        return $this;
    }

    /**
     * @param SessionNamespace $session
     * @return $this
     */
    public function setSession(SessionNamespace $session)
    {
        $this->session = $session;
        return $this;
    }

    public function onSuccess()
    {
        $changes = ProcessChanges::construct($this->bp, $this->session);

        $nodesToCleanup = $this->getValue('cleanup_all') === '1'
            ? array_keys($this->bp->getMissingChildren())
            : $this->getValue('nodes');

        foreach ($nodesToCleanup as $nodeName) {
            $node = $this->bp->getNode($nodeName);
            foreach ($node->getParents() as $parent) {
                $parentStateOverrides = $parent->getStateOverrides();

                unset($parentStateOverrides[$nodeName]);
                $changes->modifyNode($parent, ['stateOverrides' => $parentStateOverrides]);
            }

            $changes->deleteNode($node);
        }

        // TODO: figure out why this is necessary, might be an unclean shutdown on redirect
        unset($changes);

        parent::onSuccess();
    }
}
