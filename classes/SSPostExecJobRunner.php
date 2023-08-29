<?php

use Maclof\Kubernetes\Client;
use GuzzleHttp\Exception\GuzzleException;
use Maclof\Kubernetes\Client as K8sClient;
use GuzzleHttp\Client as GuzzleClient;
use Maclof\Kubernetes\Exceptions\BadRequestException;
use Symfony\Component\Yaml\Yaml;

class SSPostExecJobRunner {
    protected Client $kubernetes_client;

    public function __construct( Client $kubernetes_client ) {
        $this->kubernetes_client = $kubernetes_client;
    }

    protected static function pluralize( string $kind ): string {
        return strtolower( substr( $kind, 0, 1 ) ) . substr( $kind, 1 ) . 's';;
    }

    public function run_job( string $manifest, bool $dry_run = false ): WP_Error|SSPostExecJob {
        $data = Yaml::parse( $manifest );

        if ( ! empty( $data['metadata']['namespace'] ) ) {
            $this->kubernetes_client->setNamespace( $data['metadata']['namespace'] );
        }

        $query_params = array();

        if ( $dry_run ) {
            $query_params['dryRun'] = 'All';
        }

        try {
            $response = $this->kubernetes_client->sendRequest( 'POST', "/{$this->pluralize( $data['kind'] )}", $query_params, json_encode( $data, JSON_NUMERIC_CHECK ), true, $data['apiVersion'] );
        } catch ( BadRequestException $e ) {
            $error = new WP_Error();

            $response = json_decode( $e->getMessage(), true );
            if ( $response !== null && @$response['kind'] == 'Status' ) {
                switch ( $response['code'] ) {
                    default: $error->add( "kubernetes-api-{$response['code']}", sprintf( _x( 'Unexpected error: %s', 'Error message', 'sspostexec' ), $response['message'] ) ); break;
                }
            }

            return $error;
        } catch ( GuzzleException $e ) {
            return new WP_Error( "kubernetes-api", $e->getMessage() );
        }

        $job = new SSPostExecJob( null, $manifest, $response );

        if ( ! $dry_run ) {
            $error = $job->save();
            if ( is_wp_error( $error ) ) {
                return $error;
            }
        }

        return $job;
    }

    public static function create( SSPostExecOptions $options ): self|WP_Error {
        $error = $options->write_certificates();
        if ( is_wp_error( $error ) ) {
            return $error;
        }

        $guzzle_opts = array(
            'base_uri' => $options->kubernetes_api,

            GuzzleHttp\RequestOptions::VERIFY => $options->verify,
        );

        if ( ! $options->use_local_credentials ) {
            $guzzle_opts[GuzzleHttp\RequestOptions::VERIFY] = $options->verify ? ( $options->certificate_authority_path ? $options->certificate_authority_path : true ) : false;

            $guzzle_opts[GuzzleHttp\RequestOptions::CERT]    = $options->certificate_password ? array( $options->certificate_path, $options->certificate_password ) : $options->certificate_path;
            $guzzle_opts[GuzzleHttp\RequestOptions::SSL_KEY] = $options->private_key_password ? array( $options->private_key_path, $options->private_key_password ) : $options->private_key_path;
        }

        $client = new GuzzleClient( $guzzle_opts );

        return new SSPostExecJobRunner( new K8sClient( array(
            'master' => $options->kubernetes_api,
        ), $client ) );
    }
}
