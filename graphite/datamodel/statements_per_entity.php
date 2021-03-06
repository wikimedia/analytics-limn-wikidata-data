#!/usr/bin/php
<?php

/**
 * @author Addshore
 */

require_once( __DIR__ . '/../../src/WikimediaDb.php' );
$counter = new WikidataStatementCounter();
$counter->execute();

class WikidataStatementCounter{

	public function execute() {
		$pdo = WikimediaDb::getPdo();
		$queryResult = $pdo->query( file_get_contents( __DIR__ . '/sql/select_statements_per_entity.sql' ) );

		if( $queryResult === false ) {
			throw new RuntimeException( "Something went wrong with the db query" );
		}

		$rows = $queryResult->fetchAll();

		$totals = array(
			'item' => 0,
			'property' => 0,
		);
		$maxes = array(
			'item' => 0,
			'property' => 0,
		);
		$entitiesWithStatements = array(
			'item' => 0,
			'property' => 0,
		);
		foreach( $rows as $row ) {
			if( $row['namespace'] == '0' ) {
				$entityType = 'item';
			} elseif( $row['namespace'] == '120' ) {
				$entityType = 'property';
			} else {
				throw new LogicException( 'Couldn\'t identify namespace: ' . $row['namespace'] );
			}

			$totals[$entityType] += ( $row['statements'] * $row['count'] );
			$entitiesWithStatements[$entityType] += $row['count'];

			$this->sendMetric(
				"daily.wikidata.datamodel.$entityType.statements.count." . $row['statements'],
				$row['count']
			);

			if( $maxes[$entityType] < $row['statements'] ) {
				$maxes[$entityType] = $row['statements'];
			}
		}

		foreach( $totals as $entityType => $value ) {
			$this->sendMetric(
				"daily.wikidata.datamodel.$entityType.statements.total",
				$value
			);
			$this->sendMetric(
				"daily.wikidata.datamodel.$entityType.statements.avg",
				$value / $entitiesWithStatements[$entityType]
			);
		}

		foreach( $maxes as $entityType => $value ) {
			$this->sendMetric(
				"daily.wikidata.datamodel.$entityType.statements.max",
				$value
			);
			$this->sendMetric(
				"daily.wikidata.datamodel.$entityType.hasStatements",
				$entitiesWithStatements[$entityType]
			);
		}

	}

	private function sendMetric( $name, $value ) {
		exec( "echo \"$name $value `date +%s`\" | nc -q0 graphite.eqiad.wmnet 2003" );
	}

}
