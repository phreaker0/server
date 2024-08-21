<?php
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Test\DB\QueryBuilder\Partitioned;

use OC\DB\QueryBuilder\Partitioned\PartitionedQueryBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class PartitionedQueryBuilderTest extends TestCase {
	private IDBConnection $connection;

	protected function setUp(): void {
		$this->connection = Server::get(IDBConnection::class);

		$this->setupFileCache();
	}

	protected function tearDown(): void {
		$this->cleanupDb();
		parent::tearDown();
	}


	private function getQueryBuilder(): PartitionedQueryBuilder {
		$builder = $this->connection->getQueryBuilder();
		if ($builder instanceof PartitionedQueryBuilder) {
			return $builder;
		} else {
			return new PartitionedQueryBuilder($builder);
		}
	}

	private function setupFileCache() {
		$this->cleanupDb();
		$query = $this->getQueryBuilder();
		$query->insert('storages')
			->values([
				'numeric_id' => $query->createNamedParameter(1001001, IQueryBuilder::PARAM_INT),
				'id' => $query->createNamedParameter('test1'),
			]);
		$query->executeStatement();

		$query = $this->getQueryBuilder();
		$query->insert('filecache')
			->values([
				'storage' => $query->createNamedParameter(1001001, IQueryBuilder::PARAM_INT),
				'path' => $query->createNamedParameter('file1'),
				'path_hash' => $query->createNamedParameter(md5('file1')),
			]);
		$query->executeStatement();
		$fileId = $query->getLastInsertId();

		$query = $this->getQueryBuilder();
		$query->insert('filecache_extended')
			->hintShardKey('storage', 1001001)
			->values([
				'fileid' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
				'upload_time' => $query->createNamedParameter(1234, IQueryBuilder::PARAM_INT),
			]);
		$query->executeStatement();

		$query = $this->getQueryBuilder();
		$query->insert('mounts')
			->values([
				'storage_id' => $query->createNamedParameter(1001001, IQueryBuilder::PARAM_INT),
				'user_id' => $query->createNamedParameter('partitioned_test'),
				'mount_point' => $query->createNamedParameter('/mount/point'),
				'mount_provider_class' => $query->createNamedParameter('test'),
				'root_id' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
			]);
		$query->executeStatement();
	}

	private function cleanupDb() {
		$query = $this->getQueryBuilder();
		$query->delete('storages')
			->where($query->expr()->gt('numeric_id', $query->createNamedParameter(1000000, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$query = $this->getQueryBuilder();
		$query->delete('filecache')
			->where($query->expr()->gt('storage', $query->createNamedParameter(1000000, IQueryBuilder::PARAM_INT)))
			->runAcrossAllShards();
		$query->executeStatement();

		$query = $this->getQueryBuilder();
		$query->delete('filecache_extended')
			->runAcrossAllShards();
		$query->executeStatement();

		$query = $this->getQueryBuilder();
		$query->delete('mounts')
			->where($query->expr()->like('user_id', $query->createNamedParameter('partitioned_%')));
		$query->executeStatement();
	}

	public function testSimpleOnlyPartitionQuery() {
		$builder = $this->getQueryBuilder();
		$builder->addPartition(new PartitionSplit('filecache', ['filecache']));

		// query borrowed from UserMountCache
		$query = $builder->select('path')
			->from('filecache')
			->where($builder->expr()->eq('storage', $builder->createNamedParameter(1001001, IQueryBuilder::PARAM_INT)));

		$results = $query->executeQuery()->fetchAll();
		$this->assertCount(1, $results);
		$this->assertEquals($results[0]['path'], 'file1');
	}

	public function testSimplePartitionedQuery() {
		$builder = $this->getQueryBuilder();
		$builder->addPartition(new PartitionSplit('filecache', ['filecache']));

		// query borrowed from UserMountCache
		$query = $builder->select('storage_id', 'root_id', 'user_id', 'mount_point', 'mount_id', 'f.path', 'mount_provider_class')
			->from('mounts', 'm')
			->innerJoin('m', 'filecache', 'f', $builder->expr()->eq('m.root_id', 'f.fileid'))
			->where($builder->expr()->eq('storage_id', $builder->createNamedParameter(1001001, IQueryBuilder::PARAM_INT)));

		$query->andWhere($builder->expr()->eq('user_id', $builder->createNamedParameter('partitioned_test')));

		$this->assertEquals(2, $query->getPartitionCount());

		$results = $query->executeQuery()->fetchAll();
		$this->assertCount(1, $results);
		$this->assertEquals($results[0]['user_id'], 'partitioned_test');
		$this->assertEquals($results[0]['mount_point'], '/mount/point');
		$this->assertEquals($results[0]['mount_provider_class'], 'test');
		$this->assertEquals($results[0]['path'], 'file1');
	}

	public function testMultiTablePartitionedQuery() {
		$builder = $this->getQueryBuilder();
		$builder->addPartition(new PartitionSplit('filecache', ['filecache', 'filecache_extended']));

		$query = $builder->select('storage_id', 'root_id', 'user_id', 'mount_point', 'mount_id', 'f.path', 'mount_provider_class', 'fe.upload_time')
			->from('mounts', 'm')
			->innerJoin('m', 'filecache', 'f', $builder->expr()->eq('m.root_id', 'f.fileid'))
			->innerJoin('f', 'filecache_extended', 'fe', $builder->expr()->eq('f.fileid', 'fe.fileid'))
			->where($builder->expr()->eq('storage_id', $builder->createNamedParameter(1001001, IQueryBuilder::PARAM_INT)));

		$query->andWhere($builder->expr()->eq('user_id', $builder->createNamedParameter('partitioned_test')));

		$this->assertEquals(2, $query->getPartitionCount());

		$results = $query->executeQuery()->fetchAll();
		$this->assertCount(1, $results);
		$this->assertEquals($results[0]['user_id'], 'partitioned_test');
		$this->assertEquals($results[0]['mount_point'], '/mount/point');
		$this->assertEquals($results[0]['mount_provider_class'], 'test');
		$this->assertEquals($results[0]['path'], 'file1');
		$this->assertEquals($results[0]['upload_time'], 1234);
	}

	public function testPartitionedQueryFromSplit() {
		$builder = $this->getQueryBuilder();
		$builder->addPartition(new PartitionSplit('filecache', ['filecache']));

		$query = $builder->select('storage', 'm.root_id', 'm.user_id', 'm.mount_point', 'm.mount_id', 'path', 'm.mount_provider_class')
			->from('filecache', 'f')
			->innerJoin('f', 'mounts', 'm', $builder->expr()->eq('m.root_id', 'f.fileid'));
		$query->where($builder->expr()->eq('storage', $builder->createNamedParameter(1001001, IQueryBuilder::PARAM_INT)));

		$query->andWhere($builder->expr()->eq('m.user_id', $builder->createNamedParameter('partitioned_test')));

		$this->assertEquals(2, $query->getPartitionCount());

		$results = $query->executeQuery()->fetchAll();
		$this->assertCount(1, $results);
		$this->assertEquals($results[0]['user_id'], 'partitioned_test');
		$this->assertEquals($results[0]['mount_point'], '/mount/point');
		$this->assertEquals($results[0]['mount_provider_class'], 'test');
		$this->assertEquals($results[0]['path'], 'file1');
	}

	public function testMultiJoinPartitionedQuery() {
		$builder = $this->getQueryBuilder();
		$builder->addPartition(new PartitionSplit('filecache', ['filecache']));

		// query borrowed from UserMountCache
		$query = $builder->select('storage_id', 'root_id', 'user_id', 'mount_point', 'mount_id', 'f.path', 'mount_provider_class')
			->selectAlias('s.id', 'storage_string_id')
			->from('mounts', 'm')
			->innerJoin('m', 'filecache', 'f', $builder->expr()->eq('m.root_id', 'f.fileid'))
			->innerJoin('f', 'storages', 's', $builder->expr()->eq('f.storage', 's.numeric_id'))
			->where($builder->expr()->eq('storage_id', $builder->createNamedParameter(1001001, IQueryBuilder::PARAM_INT)));

		$query->andWhere($builder->expr()->eq('user_id', $builder->createNamedParameter('partitioned_test')));

		$this->assertEquals(3, $query->getPartitionCount());

		$results = $query->executeQuery()->fetchAll();
		$this->assertCount(1, $results);
		$this->assertEquals($results[0]['user_id'], 'partitioned_test');
		$this->assertEquals($results[0]['mount_point'], '/mount/point');
		$this->assertEquals($results[0]['mount_provider_class'], 'test');
		$this->assertEquals($results[0]['path'], 'file1');
		$this->assertEquals($results[0]['storage_string_id'], 'test1');
	}
}
