<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use ServiceManager;
use Log;

class ServiceHealthChecker
{
    /**
     * Performs a post-creation health check on the given service.
     *
     * @param Service $service The service to check.
     * @return bool True if the health check is successful, false otherwise.
     */
    public function check(Service $service): bool
    {
        $request_type = Verbs::GET;
        $testResourcePath = '/';
        $typeInfo = ServiceManager::getServiceType($service->type);

        if ($typeInfo) {
            switch ($typeInfo->getGroup()) {
                case ServiceTypeGroups::DATABASE:
                    $testResourcePath = '_table';
                    break;
                default:
                    return true;
                    break;
            }
        }

        $logContext = [
            'service_name'  => $service->name,
            'service_type'  => $service->type,
            'resource_path' => $testResourcePath,
            'request_type'  => $request_type,
        ];

        Log::info('Performing post-creation API health check.', $logContext);

        try {
            $result = ServiceManager::handleRequest($service->name, $request_type, $testResourcePath);

            if ($result->getStatusCode() >= 300) {
                $errorContent = $result->getContent();
                $errorMessage = isset($errorContent['error']['message']) ? $errorContent['error']['message'] : 'No specific error message in response.';
                $summary = 'API check returned status ' . $result->getStatusCode() . '. Message: ' . $errorMessage;
                $this->logFailure($summary, null, $logContext);
                return false;
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $summary = 'Exception during API health check: ' . $e->getMessage();
            $this->logFailure($summary, $e, $logContext);
            return false;
        }
    }

    /**
     * Logs a failed health check.
     *
     * @param Service $service
     * @param string $failureSummary
     * @param \Exception|null $rootCauseException
     * @param array $logContext Base context for logging.
     */
    protected function logFailure(string $failureSummary, ?\Exception $rootCauseException = null, array $logContext = []): void
    {
        $fullLogContext = array_merge($logContext, ['summary' => $failureSummary]);
        if ($rootCauseException) {
            $fullLogContext['root_cause_class'] = get_class($rootCauseException);
            $fullLogContext['root_cause_message'] = $rootCauseException->getMessage();
        }
        Log::error('Post-creation API health check FAILED.', $fullLogContext);
    }
}
