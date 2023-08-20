<?php

namespace App\Command;

use App\Entity\Author;
use App\Entity\Item;
use App\Repository\AuthorRepository;
use App\Repository\ItemRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use function Symfony\Component\VarDumper\Dumper\esc;

#[AsCommand(
    name: 'firstload:old',
    description: 'Import library database from the previous version',
)]
class FirstloadOldCommand extends Command
{
    protected ?EntityManagerInterface $em;
    protected ?Connection             $oldDbConn;
    protected ?OutputInterface        $output;

    public function __construct( EntityManagerInterface $entityManager )
    {
        $this->em = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument( 'old-db-url', InputArgument::OPTIONAL, 'Old DB (url format)',
            'mysql://dev:dev@127.0.0.1:3306/dev_kim-www?serverVersion=10.6.12-MariaDB&charset=utf8' );
    }

    protected function execute( InputInterface $input, OutputInterface $output ): int
    {
        $output->writeln( '<fg=bright-red>WARNING : this is a FIRSTLOAD, existing data will be OVERWRITTEN during the process</>' );
        if ( !$this->getHelper( 'question' )->ask( $input, $output, new ConfirmationQuestion(
            'Are you sure you want to start the import (yes/no) ? ', false ) ) ) {
            return Command::SUCCESS;
        }
        $this->output = $output;

        // Old version does not have doctrine style entites.
        $this->oldDbConn = DriverManager::getConnection( [
            'driver' => 'pdo_mysql',
            'url'    => $input->getArgument( 'old-db-url' ), ] );

        /** @var ItemRepository $itemRepo */
        $itemRepo = $this->em->getRepository( Item::class );
        /** @var AuthorRepository $authorRepo */
        $authorRepo = $this->em->getRepository( Author::class );

        $output->writeln( 'Import authors ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_auteurs" ) as $old ) {
            $this->newInsertOrUpdate( 'author', [ 'id' => $old['id'] ], [ 'name' => esc( trim( $old['auteur'] ) ) ] );
        }

        $output->writeln( 'Import publishers ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_editeurs" ) as $old ) {
            $this->newInsertOrUpdate( 'publisher', [ 'id' => $old['id'] ], [ 'name' => esc( trim( $old['editeur'] ) ) ] );
        }

        $output->writeln( 'Import categories ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_categories" ) as $old ) {
            $this->newInsertOrUpdate( 'category', [ 'id' => $old['id'] ], [ 'name' => esc( trim( $old['categorie'] ) ) ] );
        }

        $output->writeln( 'Import items ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_eans" ) as $old ) {
            $this->newInsertOrUpdate( 'item', [ 'ean' => $old['ean'] ], [
                'title'          => esc( trim( $old['nom'] ) ),
                'category_id'    => (int)$old['idcategorie'] ? $old['idcategorie'] : null,
                'publisher_id'   => (int)$old['idediteur'] ? $old['idediteur'] : null,
                'page_count'     => (int)$old['nbpages'],
                'published_date' => $old['datepubl'] ?? null,
                'img_width'      => (int)$old['imghaut'],
                'img_height'     => (int)$old['imglarge'],
                'created'        => $old['created'] ?? null,
                'updated'        => $old['modified'] ?? null,
            ] );
        }

        $output->writeln( 'Caching Items ids by EAN ...' );
        $itemEans = $this->newQ( sprintf( 'SELECT item.id,item.ean FROM %s as item', Item::class ) );
        foreach ( $itemEans as $itemEan ) {
            $eanId[ $itemEan['ean'] ] = $itemEan['id'];
        }

        $output->writeln( 'Import items authors ...' );
        foreach ( $this->oldQ( "SELECT ean,idauteur FROM biblio_eans WHERE idauteur > 0" ) as $old ) {
            $this->newInsertOrUpdate( 'item_author', [ 'item_id' => $eanId[ $old['ean'] ], 'author_id' => $old['idauteur'] ], [] );
        }

        $output->writeln( 'Import items description ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_eans_descr" ) as $old ) {
            $this->newInsertOrUpdate( 'item_description', [ 'item_id' => $eanId[ $old['ean'] ], 'description' => esc( trim( $old['descr'] ) ) ] );
        }

        $output->writeln( 'Import categories leading item...' );
        foreach ( $this->oldQ( "SELECT id,eanref FROM biblio_categories WHERE eanref" ) as $old ) {
            $this->newUpdate( 'category', [ 'id' => $old['id'] ], [ 'leading_item_id' => $eanId[ $old['eanref'] ] ] );
        }

        return Command::SUCCESS;

    }

    protected function oldQ( string $sql ): array
    {
        $this->output->writeln( $sql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->oldDbConn->prepare( $sql )->executeQuery()->fetchAllAssociative();
    }

    protected function newQ( string $dql ): array
    {
        $this->output->writeln( $dql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->em->createQuery( $dql )->getArrayResult();
    }

    protected function newInsertOrUpdate( string $table, array $idxData = [], array $stdData = [] )
    {
        $idxData = array_filter( $idxData, fn( $value ) => !is_null( $value ) );
        $stdData = array_filter( $stdData, fn( $value ) => !is_null( $value ) );
        $allData = array_merge( $idxData, $stdData );

        $sql = sprintf( 'INSERT %s INTO `%s` (`%s`) VALUES ("%s") ',
            !count( $stdData ) ? 'IGNORE' : '',
            $table,
            implode( '`,`', array_keys( $allData ) ),
            implode( '","', $allData )
        );
        foreach ( $stdData as $key => $val ) {
            $sql .= sprintf( '%s`%s`="%s"', $key === array_key_first( $stdData ) ? ' ON DUPLICATE KEY UPDATE ' : ',', $key, $val );
        }

        $this->output->writeln( $sql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->em->createNativeQuery( $sql, new ResultSetMapping() )->execute();
    }

    protected function newUpdate( string $table, array $idxData = [], array $stdData = [] )
    {
        $idxData = array_filter( $idxData, fn( $value ) => !is_null( $value ) );
        $stdData = array_filter( $stdData, fn( $value ) => !is_null( $value ) );

        $sql = sprintf( 'UPDATE %s SET ', $table );
        foreach ( $stdData as $key => $val ) {
            $sql .= sprintf( '%s`%s`="%s"', $key === array_key_first( $stdData ) ? '' : ',', $key, $val );
        }
        $sql .= ' WHERE ';
        foreach ( $idxData as $key => $val ) {
            $sql .= sprintf( '%s`%s`="%s"', $key === array_key_first( $idxData ) ? '' : ' AND ', $key, $val );
        }

        $this->output->writeln( $sql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->em->createNativeQuery( $sql, new ResultSetMapping() )->execute();
    }
}
