<?php
/**
 * Helper de Estatísticas e Machine Learning
 * 
 * Implementação de algoritmos estatísticos reais para ML:
 * - Regressão Linear com mínimos quadrados
 * - Exponential Smoothing (Suavização Exponencial)
 * - Decomposição Sazonal (STL simplificado)
 * - Testes de significância estatística
 * - Cálculo de intervalos de confiança
 * 
 * @author Sistema ML Manager V8.0
 * @version 1.0.0
 */

namespace App\Helpers;

class MLStatisticsHelper
{
    // ========== REGRESSÃO LINEAR ==========
    
    /**
     * Regressão Linear usando método dos mínimos quadrados (OLS)
     * 
     * @param array $x Valores do eixo X (independente)
     * @param array $y Valores do eixo Y (dependente)
     * @return array Coeficientes e métricas: slope, intercept, r_squared, std_error
     */
    public static function linearRegression(array $x, array $y): array
    {
        $n = count($x);
        
        if ($n < 2 || $n !== count($y)) {
            return [
                'slope' => 0,
                'intercept' => array_sum($y) / max($n, 1),
                'r_squared' => 0,
                'std_error' => 0,
                'valid' => false
            ];
        }
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }
        
        $meanX = $sumX / $n;
        $meanY = $sumY / $n;
        
        // Cálculo do slope (coeficiente angular) e intercept
        $denominator = $n * $sumX2 - $sumX * $sumX;
        if ($denominator == 0) {
            return [
                'slope' => 0,
                'intercept' => $meanY,
                'r_squared' => 0,
                'std_error' => 0,
                'valid' => false
            ];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = $meanY - $slope * $meanX;
        
        // Coeficiente de determinação (R²)
        $ssRes = 0;
        $ssTot = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $x[$i] + $intercept;
            $ssRes += pow($y[$i] - $predicted, 2);
            $ssTot += pow($y[$i] - $meanY, 2);
        }
        
        $rSquared = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;
        $stdError = $n > 2 ? sqrt($ssRes / ($n - 2)) : 0;
        
        return [
            'slope' => round($slope, 6),
            'intercept' => round($intercept, 6),
            'r_squared' => round(max(0, min(1, $rSquared)), 4),
            'std_error' => round($stdError, 4),
            'valid' => true
        ];
    }
    
    // ========== SUAVIZAÇÃO EXPONENCIAL ==========
    
    /**
     * Simple Exponential Smoothing (SES)
     * 
     * @param array $data Série temporal
     * @param float $alpha Parâmetro de suavização (0-1)
     * @param int $horizon Horizonte de previsão
     * @return array Previsões e métricas
     */
    public static function exponentialSmoothing(array $data, float $alpha = 0.3, int $horizon = 30): array
    {
        $n = count($data);
        
        if ($n === 0) {
            return ['forecast' => [], 'level' => 0, 'mape' => 100];
        }
        
        // Extrair valores se for array associativo
        $values = array_map(fn($item) => is_array($item) ? ($item['value'] ?? 0) : $item, $data);
        $values = array_values($values);
        $n = count($values);
        
        if ($n === 0) {
            return ['forecast' => [], 'level' => 0, 'mape' => 100];
        }
        
        // Inicialização
        $level = $values[0];
        $smoothed = [$level];
        $errors = [];
        
        // Suavização
        for ($i = 1; $i < $n; $i++) {
            $previousLevel = $level;
            $level = $alpha * $values[$i] + (1 - $alpha) * $level;
            $smoothed[] = $level;
            
            // Erro de previsão
            if ($previousLevel != 0) {
                $errors[] = abs(($values[$i] - $previousLevel) / max(abs($values[$i]), 0.01));
            }
        }
        
        // MAPE (Mean Absolute Percentage Error)
        $mape = count($errors) > 0 ? (array_sum($errors) / count($errors)) * 100 : 0;
        
        // Previsão futura (flat projection para SES)
        $forecast = [];
        for ($i = 1; $i <= $horizon; $i++) {
            $forecast[] = round($level, 2);
        }
        
        return [
            'forecast' => $forecast,
            'level' => round($level, 2),
            'mape' => round($mape, 2),
            'smoothed_series' => array_map(fn($v) => round($v, 2), $smoothed)
        ];
    }
    
    /**
     * Holt's Linear Trend (Double Exponential Smoothing)
     * Captura tendência linear junto com nível
     * 
     * @param array $data Série temporal
     * @param float $alpha Parâmetro de suavização do nível (0-1)
     * @param float $beta Parâmetro de suavização da tendência (0-1)
     * @param int $horizon Horizonte de previsão
     * @return array Previsões e métricas
     */
    public static function holtLinearTrend(array $data, float $alpha = 0.3, float $beta = 0.1, int $horizon = 30): array
    {
        // Extrair valores
        $values = array_map(fn($item) => is_array($item) ? ($item['value'] ?? 0) : $item, $data);
        $values = array_values($values);
        $n = count($values);
        
        if ($n < 2) {
            return ['forecast' => array_fill(0, $horizon, $values[0] ?? 0), 'level' => $values[0] ?? 0, 'trend' => 0];
        }
        
        // Inicialização
        $level = $values[0];
        $trend = $values[1] - $values[0];
        $errors = [];
        
        // Suavização
        for ($i = 1; $i < $n; $i++) {
            $previousLevel = $level;
            $previousTrend = $trend;
            
            $level = $alpha * $values[$i] + (1 - $alpha) * ($previousLevel + $previousTrend);
            $trend = $beta * ($level - $previousLevel) + (1 - $beta) * $previousTrend;
            
            // Erro de previsão
            $forecast = $previousLevel + $previousTrend;
            if ($values[$i] != 0) {
                $errors[] = abs(($values[$i] - $forecast) / max(abs($values[$i]), 0.01));
            }
        }
        
        // MAPE
        $mape = count($errors) > 0 ? (array_sum($errors) / count($errors)) * 100 : 0;
        
        // Previsão futura
        $forecast = [];
        for ($i = 1; $i <= $horizon; $i++) {
            $forecast[] = round($level + $trend * $i, 2);
        }
        
        return [
            'forecast' => $forecast,
            'level' => round($level, 2),
            'trend' => round($trend, 4),
            'trend_direction' => $trend > 0.01 ? 'upward' : ($trend < -0.01 ? 'downward' : 'stable'),
            'mape' => round($mape, 2)
        ];
    }
    
    /**
     * Holt-Winters Seasonal (Triple Exponential Smoothing)
     * Captura nível, tendência e sazonalidade
     * 
     * @param array $data Série temporal
     * @param int $seasonPeriod Período sazonal (7=semanal, 12=mensal, 365=anual)
     * @param float $alpha Parâmetro de suavização do nível
     * @param float $beta Parâmetro de suavização da tendência
     * @param float $gamma Parâmetro de suavização sazonal
     * @param int $horizon Horizonte de previsão
     * @return array Previsões com decomposição
     */
    public static function holtWintersSeasonal(
        array $data, 
        int $seasonPeriod = 7, 
        float $alpha = 0.3, 
        float $beta = 0.1, 
        float $gamma = 0.1, 
        int $horizon = 30
    ): array {
        $values = array_map(fn($item) => is_array($item) ? ($item['value'] ?? 0) : $item, $data);
        $values = array_values($values);
        $n = count($values);
        
        // Precisamos de pelo menos 2 períodos sazonais completos
        if ($n < $seasonPeriod * 2) {
            // Fallback para Holt Linear
            return self::holtLinearTrend($values, $alpha, $beta, $horizon);
        }
        
        // Inicialização do nível (média do primeiro período)
        $level = array_sum(array_slice($values, 0, $seasonPeriod)) / $seasonPeriod;
        
        // Inicialização da tendência
        $trend = 0;
        for ($i = 0; $i < $seasonPeriod; $i++) {
            $trend += ($values[$seasonPeriod + $i] - $values[$i]) / $seasonPeriod;
        }
        $trend /= $seasonPeriod;
        
        // Inicialização dos fatores sazonais (multiplicativos)
        $seasonal = [];
        for ($i = 0; $i < $seasonPeriod; $i++) {
            $seasonal[$i] = $level > 0 ? $values[$i] / $level : 1;
        }
        
        // Suavização
        for ($i = $seasonPeriod; $i < $n; $i++) {
            $previousLevel = $level;
            $previousTrend = $trend;
            $seasonIndex = $i % $seasonPeriod;
            
            // Atualizar nível
            $level = $alpha * ($values[$i] / max($seasonal[$seasonIndex], 0.01)) 
                   + (1 - $alpha) * ($previousLevel + $previousTrend);
            
            // Atualizar tendência
            $trend = $beta * ($level - $previousLevel) + (1 - $beta) * $previousTrend;
            
            // Atualizar fator sazonal
            $seasonal[$seasonIndex] = $gamma * ($values[$i] / max($level, 0.01)) 
                                     + (1 - $gamma) * $seasonal[$seasonIndex];
        }
        
        // Previsão futura
        $forecast = [];
        for ($i = 1; $i <= $horizon; $i++) {
            $seasonIndex = ($n + $i - 1) % $seasonPeriod;
            $forecast[] = round(($level + $trend * $i) * $seasonal[$seasonIndex], 2);
        }
        
        return [
            'forecast' => $forecast,
            'level' => round($level, 2),
            'trend' => round($trend, 4),
            'seasonal_factors' => array_map(fn($s) => round($s, 4), $seasonal),
            'trend_direction' => $trend > 0.01 ? 'upward' : ($trend < -0.01 ? 'downward' : 'stable'),
            'seasonality_strength' => round(self::seasonalityStrength($seasonal), 4)
        ];
    }
    
    /**
     * Calcula força da sazonalidade (0 = sem sazonalidade, 1 = forte sazonalidade)
     */
    private static function seasonalityStrength(array $seasonal): float
    {
        if (count($seasonal) < 2) return 0;
        
        $mean = array_sum($seasonal) / count($seasonal);
        $variance = 0;
        foreach ($seasonal as $s) {
            $variance += pow($s - $mean, 2);
        }
        $variance /= count($seasonal);
        
        // Quanto maior a variância dos fatores sazonais, mais forte a sazonalidade
        return min(1, sqrt($variance));
    }
    
    // ========== TESTES ESTATÍSTICOS ==========
    
    /**
     * Teste t de Student para significância estatística
     * 
     * @param array $sample1 Primeira amostra
     * @param array $sample2 Segunda amostra (se null, testa contra média=0)
     * @param float $alpha Nível de significância (0.05 = 95% confiança)
     * @return array Resultados do teste
     */
    public static function tTest(array $sample1, ?array $sample2 = null, float $alpha = 0.05): array
    {
        $n1 = count($sample1);
        
        if ($n1 < 2) {
            return ['significant' => false, 't_statistic' => 0, 'p_value' => 1, 'valid' => false];
        }
        
        $mean1 = array_sum($sample1) / $n1;
        $var1 = self::variance($sample1);
        
        if ($sample2 === null) {
            // One-sample t-test (H0: mean = 0)
            $tStat = $mean1 / (sqrt($var1 / $n1) + 0.0001);
            $df = $n1 - 1;
        } else {
            // Two-sample t-test
            $n2 = count($sample2);
            if ($n2 < 2) {
                return ['significant' => false, 't_statistic' => 0, 'p_value' => 1, 'valid' => false];
            }
            
            $mean2 = array_sum($sample2) / $n2;
            $var2 = self::variance($sample2);
            
            // Welch's t-test (não assume variâncias iguais)
            $se = sqrt($var1 / $n1 + $var2 / $n2);
            $tStat = $se > 0 ? ($mean1 - $mean2) / $se : 0;
            
            // Graus de liberdade (Welch-Satterthwaite)
            $num = pow($var1 / $n1 + $var2 / $n2, 2);
            $denom = pow($var1 / $n1, 2) / ($n1 - 1) + pow($var2 / $n2, 2) / ($n2 - 1);
            $df = $denom > 0 ? $num / $denom : 1;
        }
        
        // Aproximação do p-valor usando distribuição t
        $pValue = self::approximateTDistributionPValue(abs($tStat), $df);
        
        return [
            'significant' => $pValue < $alpha,
            't_statistic' => round($tStat, 4),
            'p_value' => round($pValue, 4),
            'degrees_of_freedom' => round($df, 2),
            'confidence_level' => round((1 - $pValue) * 100, 2) . '%',
            'valid' => true
        ];
    }
    
    /**
     * Aproximação do p-valor da distribuição t
     * Usa aproximação de Student-t para distribuição normal quando df > 30
     */
    private static function approximateTDistributionPValue(float $t, float $df): float
    {
        if ($df <= 0) return 1;
        
        // Para df grande, t aproxima-se de z (normal)
        if ($df > 30) {
            return 2 * self::normalCDF(-abs($t));
        }
        
        // Aproximação para df pequeno usando série
        $x = $df / ($df + $t * $t);
        $p = self::incompleteBeta($df / 2, 0.5, $x);
        
        return max(0, min(1, $p));
    }
    
    /**
     * CDF da distribuição normal padrão (aproximação)
     */
    private static function normalCDF(float $x): float
    {
        // Aproximação de Abramowitz and Stegun
        $a1 =  0.254829592;
        $a2 = -0.284496736;
        $a3 =  1.421413741;
        $a4 = -1.453152027;
        $a5 =  1.061405429;
        $p  =  0.3275911;
        
        $sign = $x < 0 ? -1 : 1;
        $x = abs($x) / sqrt(2);
        
        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);
        
        return 0.5 * (1.0 + $sign * $y);
    }
    
    /**
     * Função Beta incompleta (aproximação simplificada)
     */
    private static function incompleteBeta(float $a, float $b, float $x): float
    {
        if ($x <= 0) return 0;
        if ($x >= 1) return 1;
        
        // Aproximação usando série
        $bt = exp(log($x) * $a + log(1 - $x) * $b);
        
        if ($x < ($a + 1) / ($a + $b + 2)) {
            return $bt * self::betaContinuedFraction($a, $b, $x) / $a;
        }
        
        return 1 - $bt * self::betaContinuedFraction($b, $a, 1 - $x) / $b;
    }
    
    /**
     * Fração contínua para função Beta incompleta
     */
    private static function betaContinuedFraction(float $a, float $b, float $x): float
    {
        $fpmin = 1.0e-30;
        $c = 1.0;
        $d = 1.0 - ($a + $b) * $x / ($a + 1);
        if (abs($d) < $fpmin) $d = $fpmin;
        $d = 1.0 / $d;
        $h = $d;
        
        for ($m = 1; $m <= 100; $m++) {
            $m2 = 2 * $m;
            
            $aa = $m * ($b - $m) * $x / (($a + $m2 - 1) * ($a + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) $d = $fpmin;
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) $c = $fpmin;
            $d = 1.0 / $d;
            $h *= $d * $c;
            
            $aa = -($a + $m) * ($a + $b + $m) * $x / (($a + $m2) * ($a + $m2 + 1));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) $d = $fpmin;
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) $c = $fpmin;
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;
            
            if (abs($del - 1.0) < 3.0e-7) break;
        }
        
        return $h;
    }
    
    // ========== MÉTRICAS ESTATÍSTICAS ==========
    
    /**
     * Variância amostral
     */
    public static function variance(array $data): float
    {
        $n = count($data);
        if ($n < 2) return 0;
        
        $mean = array_sum($data) / $n;
        $sumSquares = 0;
        foreach ($data as $value) {
            $sumSquares += pow($value - $mean, 2);
        }
        
        return $sumSquares / ($n - 1);
    }
    
    /**
     * Desvio padrão amostral
     */
    public static function standardDeviation(array $data): float
    {
        return sqrt(self::variance($data));
    }
    
    /**
     * Intervalo de confiança para a média
     */
    public static function confidenceInterval(array $data, float $confidenceLevel = 0.95): array
    {
        $n = count($data);
        if ($n < 2) {
            $mean = count($data) > 0 ? $data[0] : 0;
            return ['lower' => $mean, 'upper' => $mean, 'mean' => $mean, 'margin' => 0];
        }
        
        $mean = array_sum($data) / $n;
        $stdErr = self::standardDeviation($data) / sqrt($n);
        
        // Valor z para intervalo de confiança (aproximação)
        $zValues = [0.90 => 1.645, 0.95 => 1.96, 0.99 => 2.576];
        $z = $zValues[$confidenceLevel] ?? 1.96;
        
        $margin = $z * $stdErr;
        
        return [
            'lower' => round($mean - $margin, 4),
            'upper' => round($mean + $margin, 4),
            'mean' => round($mean, 4),
            'margin' => round($margin, 4)
        ];
    }
    
    /**
     * Coeficiente de correlação de Pearson
     */
    public static function correlation(array $x, array $y): float
    {
        $n = min(count($x), count($y));
        if ($n < 2) return 0;
        
        $x = array_slice($x, 0, $n);
        $y = array_slice($y, 0, $n);
        
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        
        $covariance = 0;
        $varX = 0;
        $varY = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $covariance += $dx * $dy;
            $varX += $dx * $dx;
            $varY += $dy * $dy;
        }
        
        $denominator = sqrt($varX * $varY);
        
        return $denominator > 0 ? round($covariance / $denominator, 4) : 0;
    }
    
    // ========== DETECÇÃO DE ANOMALIAS ==========
    
    /**
     * Detecção de outliers usando Z-Score
     */
    public static function detectOutliers(array $data, float $threshold = 2.5): array
    {
        $n = count($data);
        if ($n < 3) return ['outliers' => [], 'indices' => []];
        
        $mean = array_sum($data) / $n;
        $stdDev = self::standardDeviation($data);
        
        if ($stdDev == 0) return ['outliers' => [], 'indices' => []];
        
        $outliers = [];
        $indices = [];
        
        foreach ($data as $i => $value) {
            $zScore = abs($value - $mean) / $stdDev;
            if ($zScore > $threshold) {
                $outliers[] = ['value' => $value, 'z_score' => round($zScore, 2), 'index' => $i];
                $indices[] = $i;
            }
        }
        
        return [
            'outliers' => $outliers,
            'indices' => $indices,
            'mean' => round($mean, 4),
            'std_dev' => round($stdDev, 4),
            'threshold' => $threshold
        ];
    }
    
    /**
     * Detecção de mudança de tendência (Change Point Detection)
     */
    public static function detectChangePoints(array $data, int $minSegmentSize = 5): array
    {
        $n = count($data);
        if ($n < $minSegmentSize * 2) return ['change_points' => []];
        
        $changePoints = [];
        
        // Sliding window para detectar mudanças significativas
        for ($i = $minSegmentSize; $i < $n - $minSegmentSize; $i++) {
            $before = array_slice($data, $i - $minSegmentSize, $minSegmentSize);
            $after = array_slice($data, $i, $minSegmentSize);
            
            $result = self::tTest($before, $after, 0.05);
            
            if ($result['significant'] && $result['p_value'] < 0.01) {
                $changePoints[] = [
                    'index' => $i,
                    'before_mean' => round(array_sum($before) / count($before), 2),
                    'after_mean' => round(array_sum($after) / count($after), 2),
                    'p_value' => $result['p_value']
                ];
            }
        }
        
        // Filtrar pontos muito próximos (manter apenas o mais significativo)
        $filtered = [];
        $lastIdx = -$minSegmentSize * 2;
        
        foreach ($changePoints as $cp) {
            if ($cp['index'] - $lastIdx >= $minSegmentSize) {
                $filtered[] = $cp;
                $lastIdx = $cp['index'];
            }
        }
        
        return ['change_points' => $filtered];
    }
    
    // ========== DECOMPOSIÇÃO SAZONAL ==========
    
    /**
     * Decomposição sazonal simplificada (STL-like)
     * Separa série em: tendência + sazonalidade + ruído
     */
    public static function seasonalDecomposition(array $data, int $period = 7): array
    {
        $values = array_map(fn($item) => is_array($item) ? ($item['value'] ?? 0) : $item, $data);
        $values = array_values($values);
        $n = count($values);
        
        if ($n < $period * 2) {
            return [
                'trend' => $values,
                'seasonal' => array_fill(0, $n, 0),
                'residual' => array_fill(0, $n, 0),
                'seasonal_strength' => 0
            ];
        }
        
        // 1. Extrair tendência usando média móvel
        $trend = self::movingAverage($values, $period);
        
        // 2. Remover tendência para obter componente sazonal + ruído
        $detrended = [];
        for ($i = 0; $i < $n; $i++) {
            $detrended[$i] = $values[$i] - $trend[$i];
        }
        
        // 3. Calcular fatores sazonais médios
        $seasonalFactors = array_fill(0, $period, 0);
        $counts = array_fill(0, $period, 0);
        
        for ($i = 0; $i < $n; $i++) {
            $seasonIdx = $i % $period;
            $seasonalFactors[$seasonIdx] += $detrended[$i];
            $counts[$seasonIdx]++;
        }
        
        for ($i = 0; $i < $period; $i++) {
            $seasonalFactors[$i] = $counts[$i] > 0 ? $seasonalFactors[$i] / $counts[$i] : 0;
        }
        
        // Normalizar fatores sazonais para soma = 0
        $avgFactor = array_sum($seasonalFactors) / $period;
        $seasonalFactors = array_map(fn($f) => $f - $avgFactor, $seasonalFactors);
        
        // 4. Aplicar fatores sazonais e calcular resíduo
        $seasonal = [];
        $residual = [];
        
        for ($i = 0; $i < $n; $i++) {
            $seasonIdx = $i % $period;
            $seasonal[$i] = $seasonalFactors[$seasonIdx];
            $residual[$i] = $values[$i] - $trend[$i] - $seasonal[$i];
        }
        
        // 5. Calcular força da sazonalidade
        $varResidual = self::variance($residual);
        $varDetrended = self::variance($detrended);
        $seasonalStrength = $varDetrended > 0 ? max(0, 1 - $varResidual / $varDetrended) : 0;
        
        return [
            'trend' => array_map(fn($v) => round($v, 2), $trend),
            'seasonal' => array_map(fn($v) => round($v, 2), $seasonal),
            'residual' => array_map(fn($v) => round($v, 2), $residual),
            'seasonal_factors' => array_map(fn($v) => round($v, 4), $seasonalFactors),
            'seasonal_strength' => round($seasonalStrength, 4),
            'trend_direction' => $trend[count($trend) - 1] > $trend[0] ? 'upward' : 'downward'
        ];
    }
    
    /**
     * Média móvel centrada
     */
    public static function movingAverage(array $data, int $window): array
    {
        $n = count($data);
        $result = [];
        $halfWindow = intval($window / 2);
        
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - $halfWindow);
            $end = min($n - 1, $i + $halfWindow);
            $slice = array_slice($data, $start, $end - $start + 1);
            $result[$i] = array_sum($slice) / count($slice);
        }
        
        return $result;
    }
    
    // ========== PREVISÃO COMBINADA ==========
    
    /**
     * Ensemble de modelos - combina múltiplas previsões
     */
    public static function ensembleForecast(array $data, int $horizon = 30): array
    {
        // Executar diferentes modelos
        $ses = self::exponentialSmoothing($data, 0.3, $horizon);
        $holt = self::holtLinearTrend($data, 0.3, 0.1, $horizon);
        $hw = self::holtWintersSeasonal($data, 7, 0.3, 0.1, 0.1, $horizon);
        
        // Pesos baseados no erro (MAPE)
        $errors = [
            'ses' => max(1, $ses['mape'] ?? 100),
            'holt' => max(1, $holt['mape'] ?? 100),
            'hw' => 15 // Peso fixo moderado para HW (mais complexo)
        ];
        
        $totalWeight = 1 / $errors['ses'] + 1 / $errors['holt'] + 1 / $errors['hw'];
        
        $weights = [
            'ses' => (1 / $errors['ses']) / $totalWeight,
            'holt' => (1 / $errors['holt']) / $totalWeight,
            'hw' => (1 / $errors['hw']) / $totalWeight
        ];
        
        // Combinar previsões
        $combined = [];
        for ($i = 0; $i < $horizon; $i++) {
            $combined[$i] = round(
                $weights['ses'] * ($ses['forecast'][$i] ?? 0) +
                $weights['holt'] * ($holt['forecast'][$i] ?? 0) +
                $weights['hw'] * ($hw['forecast'][$i] ?? 0),
                2
            );
        }
        
        return [
            'forecast' => $combined,
            'models' => [
                'simple_exponential_smoothing' => ['forecast' => $ses['forecast'], 'weight' => round($weights['ses'], 4), 'mape' => $ses['mape'] ?? null],
                'holt_linear_trend' => ['forecast' => $holt['forecast'], 'weight' => round($weights['holt'], 4), 'mape' => $holt['mape'] ?? null],
                'holt_winters_seasonal' => ['forecast' => $hw['forecast'], 'weight' => round($weights['hw'], 4)]
            ],
            'trend' => $holt['trend_direction'] ?? 'stable',
            'level' => $holt['level'] ?? 0
        ];
    }
}
