<?php

namespace App\Sidecar;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Runtime;

class WeasyPrint extends LambdaFunction
{
    /**
     * The name of the handler function in the python script.
     * Points to resources/sidecar/weasyprint/pdf.py -> lambda_handler
     */
    public function handler()
    {
        return 'resources/sidecar/weasyprint/pdf.lambda_handler';
    }

    /**
     * The runtime environment to execute the function.
     * Matches the Python version compiled in the kotify/cloud-print-utils layer.
     */
    public function runtime()
    {
        return Runtime::PYTHON_312;
    }

    /**
     * The architecture for the Lambda function.
     */
    public function architecture()
    {
        return \Hammerstone\Sidecar\Architecture::ARM_64;
    }

    /**
     * Custom name for the function.
     */
    public function name()
    {
        return 'weasyprint';
    }

    /**
     * Memory allocated for the function (in MB).
     * WeasyPrint can be memory-heavy, so 1024MB is recommended.
     */
    public function memory()
    {
        return 1024;
    }

    /**
     * Timeout for function execution (in seconds).
     */
    public function timeout()
    {
        return 30;
    }

    /**
     * Lambda Layers attached to the function.
     * References the WeasyPrint Layer ARN from the .env file.
     */
    public function layers()
    {
        $layerArn = config('erp.weasyprint_layer_arn');

        if (empty($layerArn)) {
            // Provide a fallback or descriptive warning if env is missing
            throw new \Exception('WEASYPRINT_LAYER_ARN is not configured in your .env file.');
        }

        return [
            $layerArn,
        ];
    }

    /**
     * Environment variables configured in AWS Lambda.
     */
    public function variables()
    {
        return [
            // Tells Python where to look for WeasyPrint and dependencies
            'PYTHONPATH' => '/opt/python:/opt/python/lib/python3.12/site-packages:/opt',
            // Tells linker where to find Cairo, Pango, and other libraries
            'LD_LIBRARY_PATH' => '/var/lang/lib:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib:/opt/lib',
            // Tells Fontconfig where to find layer fonts
            'FONTCONFIG_PATH' => '/opt/fonts',
        ];
    }

    /**
     * The files/directories to package and deploy.
     */
    public function package()
    {
        return [
            'resources/sidecar/weasyprint',
        ];
    }

    /**
     * Checks if the AWS Lambda WeasyPrint is fully setup and configured.
     */
    public static function isSetup(): bool
    {
        return ! app()->runningUnitTests()
            && ! empty(config('erp.weasyprint_layer_arn'))
            && ! empty(config('sidecar.aws_key'));
    }
}
