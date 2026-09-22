<?php

namespace App\EventSubscriber;

use App\Entity\Lead;
use App\Entity\StatusHistory;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StatusHistorySubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function onBeforeEntityUpdatedEvent(BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!$entity instanceof Lead) {
            return;
        }

        $unitOfWork = $this->entityManager->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        if (!isset($changeSet['status'])) {
            return;
        }

        [$oldStatus, $newStatus] = $changeSet['status'];

        if ($oldStatus === $newStatus) {
            return;
        }

        $statusHistory = new StatusHistory();
        $statusHistory
            ->setOldStatus($oldStatus)
            ->setNewStatus($newStatus)
            ->setChangedAt(new \DateTimeImmutable())
            ->setLead($entity);

        $this->entityManager->persist($statusHistory);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityUpdatedEvent::class => 'onBeforeEntityUpdatedEvent',
        ];
    }
}