#!/usr/bin/php
<?php

/**
 * @author Addshore
 *
 * Get minutely stats about the state of the query engine
 *  - Number of triples
 *  - Lag of the store
 */

require_once( __DIR__ . '/../../src/WikimediaCurl.php' );

$metrics = new WikidataSparqlTriples();
$metrics->execute( 'http://wdqs1001.eqiad.wmnet:8888/sparql' );
$metrics->execute( 'http://wdqs1002.eqiad.wmnet:8888/sparql' );

class WikidataSparqlTriples{

	public function execute( $host ) {
		// WDQS currently caches for 120 seconds, avoid this by adding whitespace
		$whitespace = str_repeat( ' ', date( 'i' ) );

		$query = "prefix schema: <http://schema.org/>";
		$query .= "SELECT * WHERE { {";
		$query .= "SELECT ( COUNT( * ) AS ?count ) { ?s ?p ?o } ";
		$query .= "} UNION {";
		$query .= "SELECT * WHERE { <http://www.wikidata.org> schema:dateModified ?y }";
		$query .= "} $whitespace }";

		$response = WikimediaCurl::curlGet( $host . "?format=json&query=" . urlencode( $query ) );

		if( $response === false ) {
			throw new RuntimeException( "The SPARQL request failed!" );
		}

		$headers = $response[0];
		$servedBy = $headers['X-Served-By'];
		$data = json_decode( $response[1], true );

		foreach( $data['results']['bindings'] as $binding ) {

			if( array_key_exists( 'count', $binding ) ) {
				$tripleCount = $binding['count']['value'];
				exec( "echo \"wikidata.query.triples.$servedBy $tripleCount `date +%s`\" | nc -q0 graphite.eqiad.wmnet 2003" );
			} elseif( array_key_exists( 'y', $binding ) ) {
				$lastUpdated = $binding['y']['value'];
				$lag = time() - strtotime( $lastUpdated );
				exec( "echo \"wikidata.query.lag.$servedBy $lag `date +%s`\" | nc -q0 graphite.eqiad.wmnet 2003" );
			} else {
				trigger_error( "SPARQL binding returned with unexpected keys " . json_encode( $binding ), E_USER_WARNING );
			}

		}

	}

}
