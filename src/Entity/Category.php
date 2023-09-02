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

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

#[Gedmo\Tree( type: 'nested' )]
#[ORM\Entity( repositoryClass: CategoryRepository::class )]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column( length: 255 )]
    private ?string $name = null;

    #[ORM\OneToMany( mappedBy: 'category', targetEntity: Item::class )]
    private Collection $items;

    #[ORM\OneToOne( cascade: [ 'persist', 'remove' ] )]
    private ?Item $leadingItem = null;

    #[ORM\Column]
    private bool $isSeries = false;

    #[ORM\Column]
    private bool $isFinished = false;

    #[ORM\Column]
    private bool $isStopped = false;

    #[ORM\Column]
    private int $seriesItemsCount = 0;

    /*
     * Tree
     */
    #[Gedmo\TreeLeft]
    #[ORM\Column( name: 'lft', type: Types::INTEGER )]
    private $lft;

    #[Gedmo\TreeLevel]
    #[ORM\Column( name: 'lvl', type: Types::INTEGER )]
    private $lvl;

    #[Gedmo\TreeRight]
    #[ORM\Column( name: 'rgt', type: Types::INTEGER )]
    private $rgt;

    #[Gedmo\TreeRoot]
    #[ORM\ManyToOne( targetEntity: Category::class )]
    #[ORM\JoinColumn( name: 'tree_root', referencedColumnName: 'id', onDelete: 'SET NULL' )]
    private $root;

    #[Gedmo\TreeParent]
    #[ORM\ManyToOne( targetEntity: Category::class, inversedBy: 'children' )]
    #[ORM\JoinColumn( name: 'parent_id', referencedColumnName: 'id', onDelete: 'SET NULL' )]
    private $parent;

    #[ORM\OneToMany( targetEntity: Category::class, mappedBy: 'parent' )]
    #[ORM\OrderBy( [ 'lft' => 'ASC' ] )]
    private $children;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName( string $name ): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem( Item $item ): static
    {
        if ( !$this->items->contains( $item ) ) {
            $this->items->add( $item );
            $item->setCategory( $this );
        }

        return $this;
    }

    public function removeItem( Item $item ): static
    {
        if ( $this->items->removeElement( $item ) ) {
            // set the owning side to null (unless already changed)
            if ( $item->getCategory() === $this ) {
                $item->setCategory( null );
            }
        }

        return $this;
    }

    public function getLeadingItem(): ?Item
    {
        return $this->leadingItem;
    }

    public function setLeadingItem( ?Item $leadingItem ): static
    {
        $this->leadingItem = $leadingItem;

        return $this;
    }

    public function isSeries(): ?bool
    {
        return $this->isSeries;
    }

    public function setIsSeries( bool $isSeries ): static
    {
        $this->isSeries = $isSeries;

        return $this;
    }

    public function isFinished(): ?bool
    {
        return $this->isFinished;
    }

    public function setIsFinished( bool $isFinished ): static
    {
        $this->isFinished = $isFinished;

        return $this;
    }

    public function isStopped(): ?bool
    {
        return $this->isStopped;
    }

    public function setIsStopped( bool $isStopped ): static
    {
        $this->isStopped = $isStopped;

        return $this;
    }

    public function getSeriesItemsCount(): ?int
    {
        return $this->seriesItemsCount;
    }

    public function setSeriesItemsCount( int $seriesItemsCount ): static
    {
        $this->seriesItemsCount = $seriesItemsCount;

        return $this;
    }

    public function getRoot(): ?self
    {
        return $this->root;
    }

    public function setParent( self $parent = null ): void
    {
        $this->parent = $parent;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }
}
