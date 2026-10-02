<?php

namespace App\Entity;

use App\Repository\EnvoiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un envoi suivi par le client depuis la page d'accueil, grâce à son numéro de suivi.
 * Seuls le numéro, les villes, l'étape et la date de mise à jour sont rendus publics.
 */
#[ORM\Entity(repositoryClass: EnvoiRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('reference')]
class Envoi
{
    /** Étapes dans l'ordre de la livraison (même ordre que sur la page d'accueil). */
    public const ETAPES = [
        'commande_recue' => 'Commande reçue',
        'chargement' => 'Chargement',
        'en_transit' => 'En transit',
        'arrivee' => 'Arrivée',
        'livree' => 'Livrée',
    ];

    /** Sans 0/O ni 1/I/L, pour éviter les erreurs de saisie. */
    private const ALPHABET_REFERENCE = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $reference;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $villeDepart = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $villeArrivee = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitudeDepart = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitudeDepart = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitudeArrivee = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitudeArrivee = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(callback: [self::class, 'codesEtapes'])]
    private string $etape = 'commande_recue';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $noteInterne = null;

    public function __construct()
    {
        $this->reference = self::genererReference();
    }

    /** Numéro du type MOSL-7K4P-2QX9 : 8 caractères aléatoires, impossible à deviner. */
    public static function genererReference(): string
    {
        $caracteres = '';
        for ($i = 0; $i < 8; ++$i) {
            $caracteres .= self::ALPHABET_REFERENCE[random_int(0, strlen(self::ALPHABET_REFERENCE) - 1)];
        }

        return sprintf('MOSL-%s-%s', substr($caracteres, 0, 4), substr($caracteres, 4));
    }

    /** Met en forme une saisie client : majuscules, sans espaces, tirets rétablis. */
    public static function normaliserReference(string $saisie): string
    {
        $brut = preg_replace('/[^A-Z0-9]/', '', strtoupper($saisie));

        if (preg_match('/^MOSL([A-Z0-9]{4})([A-Z0-9]{4})$/', $brut, $morceaux)) {
            return sprintf('MOSL-%s-%s', $morceaux[1], $morceaux[2]);
        }

        return $brut;
    }

    /** @return list<string> */
    public static function codesEtapes(): array
    {
        return array_keys(self::ETAPES);
    }

    #[ORM\PrePersist]
    public function initialiserDates(): void
    {
        $maintenant = new \DateTimeImmutable();
        $this->createdAt ??= $maintenant;
        $this->updatedAt = $maintenant;
    }

    #[ORM\PreUpdate]
    public function actualiserDate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getVilleDepart(): ?string
    {
        return $this->villeDepart;
    }

    public function setVilleDepart(string $villeDepart): static
    {
        $this->villeDepart = $villeDepart;

        return $this;
    }

    public function getVilleArrivee(): ?string
    {
        return $this->villeArrivee;
    }

    public function setVilleArrivee(string $villeArrivee): static
    {
        $this->villeArrivee = $villeArrivee;

        return $this;
    }

    public function getLatitudeDepart(): ?float
    {
        return $this->latitudeDepart;
    }

    public function getLongitudeDepart(): ?float
    {
        return $this->longitudeDepart;
    }

    public function setCoordonneesDepart(?float $latitude, ?float $longitude): static
    {
        $this->latitudeDepart = $latitude;
        $this->longitudeDepart = $longitude;

        return $this;
    }

    public function getLatitudeArrivee(): ?float
    {
        return $this->latitudeArrivee;
    }

    public function getLongitudeArrivee(): ?float
    {
        return $this->longitudeArrivee;
    }

    public function setCoordonneesArrivee(?float $latitude, ?float $longitude): static
    {
        $this->latitudeArrivee = $latitude;
        $this->longitudeArrivee = $longitude;

        return $this;
    }

    public function getEtape(): string
    {
        return $this->etape;
    }

    public function setEtape(string $etape): static
    {
        $this->etape = $etape;

        return $this;
    }

    public function getLibelleEtape(): string
    {
        return self::ETAPES[$this->etape] ?? $this->etape;
    }

    /** Position de l'étape, de 1 (Commande reçue) à 5 (Livrée). */
    public function getNumeroEtape(): int
    {
        $position = array_search($this->etape, self::codesEtapes(), true);

        return false === $position ? 1 : $position + 1;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getNoteInterne(): ?string
    {
        return $this->noteInterne;
    }

    public function setNoteInterne(?string $noteInterne): static
    {
        $this->noteInterne = $noteInterne;

        return $this;
    }
}
