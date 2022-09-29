<?php

namespace Icinga\Module\Businessprocess\Clicommands;

use Icinga\Application\Logger;
use Icinga\Application\Modules\Module;
use Icinga\Cli\Command;
use Icinga\Module\Businessprocess\Modification\NodeModifyAction;
use Icinga\Module\Businessprocess\Modification\NodeRemoveAction;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class CleanupCommand extends Command
{
    /**
     * @var LegacyStorage
     */
    protected $storage;

    protected $defaultActionName = 'cleanup';

    public function init()
    {
        $this->storage = LegacyStorage::getInstance();
    }

    /**
     * Cleanup all missing monitoring nodes from the specified config name
     * If no config name is specified, the missing nodes are cleaned from all available configs
     *
     * USAGE
     *
     * icingacli businessprocess cleanup [<config-name>]
     *
     * OPTIONS
     *
     *   <config-name>
     */
    public function cleanupAction()
    {
        $configNames = (array) $this->params->shift() ?: $this->storage->listAllProcessNames();
        $missingChildren = $this->listAllMissingChildren($configNames);
        $removedNodes = [];

        foreach ($missingChildren as $nodeName) {
            foreach ($configNames as $configName) {
                if ($this->storage->hasProcess($configName)) {
                    $bp = $this->storage->loadProcess($configName);
                    if ($bp->hasNode($nodeName)) {
                        $node = $bp->getNode($nodeName);
                        foreach ($node->getParents() as $parent) {
                            $parentStateOverrides = $parent->getStateOverrides();

                            unset($parentStateOverrides[$nodeName]);
                            $modify = new NodeModifyAction($parent);
                            $modify->setNodeProperties($parent, ['stateOverrides' => $parentStateOverrides]);
                            if ($modify->appliesTo($bp)) {
                                $modify->applyTo($bp);
                            }
                        }

                        $remove = new NodeRemoveAction($node);
                        if ($remove->appliesTo($bp)) {
                            $remove->applyTo($bp);
                            $removedNodes[] = $node->getName();
                        }

                        $this->storage->storeProcess($bp);
                        $bp->clearAppliedChanges();
                    }
                }
            }
        }

        if (count($removedNodes)) {
            echo sprintf('Removed following %d missing nodes successfully:', count($removedNodes)) . "\n";
            foreach ($removedNodes as $node) {
                echo $node . "\n";
            }
        } else {
            echo "No missing node found\n";
        }
    }

    protected function listAllMissingChildren($configNames)
    {
        $missingChildren = [];
        foreach ($configNames as $config) {
            if ($this->storage->hasProcess($config)) {
                $bp = $this->storage->loadProcess($config);
                if (Module::exists('icingadb') &&
                    (! $bp->hasBackendName() && IcingadbSupport::useIcingaDbAsBackend())
                ) {
                    IcingaDbState::apply($bp);
                } else {
                    MonitoringState::apply($bp);
                }

                $missingChildren = array_merge($missingChildren, array_keys($bp->getMissingChildren()));
            } else {
                Logger::error('Config name %s not found', $config);
            }
        }

        return $missingChildren;
    }
}
