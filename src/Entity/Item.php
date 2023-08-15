<?php
/*
 * Kimengumi Library
 *
 * Licensed under the EUPL, Version 1.2 or – as soon they will be approved by
 * the European Commission - subsequent versions of the EUPL (the "Licence");
 * You may not use this work except in compliance with the Licence.
 * You may obtain a copy of the Licence at:
 *
 * https://joinup.ec.europa.eu/software/page/eupl
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the Licence is distributed on an "AS IS" basis,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the Licence for the specific language governing permissions and
 * limitations under the Licence.
 *
 * @author Antonio Rossetti <antonio@rossetti.fr>
 * @copyright since 2023 Antonio Rossetti
 * @license <https://joinup.ec.europa.eu/software/page/eupl> EUPL
 */

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity( repositoryClass: ItemRepository::class )]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column( length: 13 )]
    private ?string $ean = null;

    #[ORM\Column( length: 255 )]
    private ?string $title = null;

    #[ORM\OneToMany( mappedBy: 'items', targetEntity: Author::class )]
    private Collection $authors;

    #[ORM\ManyToOne( inversedBy: 'items' )]
    private ?Publisher $publisher = null;

    #[ORM\Column( type: Types::DATE_MUTABLE, nullable: true )]
    private ?\DateTimeInterface $publishedDate = null;

    #[ORM\Column( nullable: true )]
    private ?int $pageCount = null;

    #[ORM\Column]
    private ?int $imgWidth = null;

    #[ORM\Column]
    private ?int $imgHeight = null;

    #[ORM\Column( type: Types::DATETIME_MUTABLE )]
    private ?\DateTimeInterface $created = null;

    #[ORM\Column( type: Types::DATETIME_MUTABLE )]
    private ?\DateTimeInterface $updated = null;

    #[ORM\OneToOne( mappedBy: 'item', cascade: [ 'persist', 'remove' ] )]
    private ?ItemDescription $description = null;

    #[ORM\ManyToOne( inversedBy: 'items' )]
    #[ORM\JoinColumn( nullable: false )]
    private ?Category $category = null;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan( string $ean ): static
    {
        $this->ean = $ean;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle( string $title ): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, Author>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor( Author $author ): static
    {
        if ( !$this->authors->contains( $author ) ) {
            $this->authors->add( $author );
            $author->setItems( $this );
        }

        return $this;
    }

    public function removeAuthor( Author $author ): static
    {
        if ( $this->authors->removeElement( $author ) ) {
            // set the owning side to null (unless already changed)
            if ( $author->getItems() === $this ) {
                $author->setItems( null );
            }
        }

        return $this;
    }

    public function getPublisher(): ?Publisher
    {
        return $this->publisher;
    }

    public function setPublisher( ?Publisher $publisher ): static
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getPublishedDate(): ?\DateTimeInterface
    {
        return $this->publishedDate;
    }

    public function setPublishedDate( ?\DateTimeInterface $publishedDate ): static
    {
        $this->publishedDate = $publishedDate;

        return $this;
    }

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount( ?int $pageCount ): static
    {
        $this->pageCount = $pageCount;

        return $this;
    }

    public function getImgWidth(): ?int
    {
        return $this->imgWidth;
    }

    public function setImgWidth( int $imgWidth ): static
    {
        $this->imgWidth = $imgWidth;

        return $this;
    }

    public function getImgHeight(): ?int
    {
        return $this->imgHeight;
    }

    public function setImgHeight( int $imgHeight ): static
    {
        $this->imgHeight = $imgHeight;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated( \DateTimeInterface $created ): static
    {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?\DateTimeInterface
    {
        return $this->updated;
    }

    public function setUpdated( \DateTimeInterface $updated ): static
    {
        $this->updated = $updated;

        return $this;
    }

    public function getDescription(): ?ItemDescription
    {
        return $this->description;
    }

    public function setDescription( ?ItemDescription $description ): static
    {
        // unset the owning side of the relation if necessary
        if ( $description === null && $this->description !== null ) {
            $this->description->setItem( null );
        }

        // set the owning side of the relation if necessary
        if ( $description !== null && $description->getItem() !== $this ) {
            $description->setItem( $this );
        }

        $this->description = $description;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory( ?Category $category ): static
    {
        $this->category = $category;

        return $this;
    }
}
