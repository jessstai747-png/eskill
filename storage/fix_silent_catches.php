<?php
/**
 * Script to add error_log() to all silent catch blocks in 3 service files.
 */

 = [
    'app/Services/MercadoLivre/CompetitorIntelligenceService.php' => 'CompetitorIntelligenceService',
    'app/Services/MercadoLivre/SmartQAService.php' => 'SmartQAService',
    'app/Services/MercadoLivre/MLAnalyticsIntelligenceService.php' => 'MLAnalyticsIntelligenceService',
];

 = 0;

foreach ( as  => ) {
     = file_get_contents();
     = explode(n, );
     = count();
     = 0;
     = [];

    for ( = 0;  < ; ++) {
        [] = [];

        // Check if this line is a catch block
        if (preg_match('/^(\s*)\}\s*catch\s*\(\\?Exception\s+$(\w+)\)\s*\{/', [], )) {
             = [1];
             = [2];
             =  . '    '; // One level deeper

            // Find enclosing method name
             = 'unknown';
            for ( = ;  >= 0; --) {
                if (preg_match('/function\s+(\w+)/', [], )) {
                     = [1];
                    break;
                }
            }

            // Check if next lines already have logging (check up to 5 lines)
             = false;
            for ( =  + 1;  < min(,  + 8); ++) {
                 = [];
                if (preg_match('/(error_log|log_error|log_warning|log_info|log_debug|$this->logger)/', )) {
                     = true;
                    break;
                }
                if (trim() === '}') {
                    break;
                }
            }

            if (
