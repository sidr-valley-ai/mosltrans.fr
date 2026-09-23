<?php

namespace App\EventSubscriber;

use App\Entity\Lead;
use App\Entity\StatusHistory;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
class StatusHistorySubscriber
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $statusHistoryMetadata = $entityManager->getClassMetadata(StatusHistory::class);

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Lead) {
                continue;
            }

            $changeSet = $unitOfWork->getEntityChangeSet($entity);

            if (!isset($changeSet['status'])) {
                continue;
            }

            [$oldStatus, $newStatus] = $changeSet['status'];

            if (!is_string($oldStatus) || !is_string($newStatus)) {
                continue;
            }

            $statusHistory = new StatusHistory();
            $statusHistory
                ->setOldStatus($oldStatus)
                ->setNewStatus($newStatus)
                ->setChangedAt(new \DateTimeImmutable());

            $entity->addStatusHistory($statusHistory);

            $entityManager->persist($statusHistory);
            $unitOfWork->computeChangeSet($statusHistoryMetadata, $statusHistory);
        }
    }
}
