    /**
     * 📊 Calcular SEO Score de um item
     * GET /api/seo-killer/score/{itemId}
     */
    public function calculateScore(string $itemId): void
    {
        $this->json(function() use ($itemId) {
            if (!$this->accountId) {
                return ['error' => 'Nenhuma conta conectada'];
            }
            
            $calculator = new SEOScoreCalculator($this->accountId);
            return $calculator->calculateScore($itemId);
        });
    }
    
    /**
     * 📊 Get real AutoPilot status from database
     * GET /api/seo-killer/autopilot/status
     */
    public function getAutopilotRealStatus(): void
    {
        $this->json(function() {
            if (!$this->accountId) {
                return ['error' => 'Nenhuma conta conectada'];
            }
            
            $statusManager = new AutoPilotStatusManager($this->accountId);
            return $statusManager->getRealStatus();
        });
    }
    
    /**
     * 🏆 Get top performing items (for dashboard)
     * GET /api/seo-killer/top-performers
     */
    public function getTopPerformingItems(): void
    {
        $this->json(function() {
            if (!$this->accountId) {
                return ['error' => 'Nenhuma conta conectada'];
            }
            
            $limit = intval($_GET['limit'] ?? 10);
            $period = $_GET['period'] ?? '30d';
            
            // Get items and calculate scores
            $itemService = new \App\Services\ItemService($this->accountId);
            $items = $itemService->listItems(['limit' => 50]);
            
            $calculator = new SEOScoreCalculator($this->accountId);
            $scoredItems = [];
            
            foreach ($items['results'] ?? [] as $item) {
                $score = $calculator->calculateScore($item['id'], $item);
                $scoredItems[] = [
                    'item_id' => $item['id'],
                    'title' => $item['title'] ?? '',
                    'price' => $item['price'] ?? 0,
                    'sold_quantity' => $item['sold_quantity'] ?? 0,
                    'score' => $score['overall_score'],
                    'grade' => $score['grade'],
                    'thumbnail' => $item['thumbnail'] ?? '',
                ];
            }
            
            // Sort by score DESC
            usort($scoredItems, fn($a, $b) => $b['score'] <=> $a['score']);
            
            return [
                'success' => true,
                'items' => array_slice($scoredItems, 0, $limit),
                'period' => $period,
            ];
        });
    }
