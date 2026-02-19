const http = require('http');

const PORT = 3100;

const server = http.createServer((req, res) => {
    // CORS headers
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    if (req.method === 'POST' && req.url === '/v1/chat/completions') {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });

        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                const messages = data.messages || [];
                const lastMessage = messages[messages.length - 1]?.content || '';
                
                let content = "I am a simulated MCP model.";

                // Simulating Contextual Responses
                if (lastMessage.includes('TRIGGER_ERROR_500')) {
                    // Simulate Provider Error (Internal Server Error)
                    res.writeHead(500, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: { message: 'Simulated Internal Server Error', type: 'server_error', code: 500 } }));
                    console.log(`[MCP Server] Simulated 500 Error`);
                    return;
                } else if (lastMessage.includes('TRIGGER_ERROR_429')) {
                    // Simulate Rate Limit Error
                    res.writeHead(429, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: { message: 'Rate limit exceeded', type: 'rate_limit_error', code: 429 } }));
                    console.log(`[MCP Server] Simulated 429 Error`);
                    return;
                } else if (lastMessage.includes('TRIGGER_MALFORMED_JSON')) {
                    // Simulate Malformed Response (Not JSON)
                    res.writeHead(200, { 'Content-Type': 'text/plain' });
                    res.end("This is not JSON");
                    console.log(`[MCP Server] Simulated Malformed JSON`);
                    return;
                } else if (lastMessage.includes('title') || lastMessage.includes('título')) {
                    // TitleOptimizer expects JSON in the content
                    content = JSON.stringify({
                        optimized_title: "iPhone 14 Pro Max 256GB Ouro - Lacrado Garantia Apple",
                        score: 98,
                        improvements: ["Adicionado capacidade", "Adicionado cor", "Adicionado condição"],
                        keywords_used: ["iPhone 14", "Pro Max", "256GB", "Apple"],
                        char_count: 55,
                        alternatives: [],
                        // Support for TitleKiller
                        titles: [
                            {
                                title: "iPhone 14 Pro Max 256GB Ouro - Lacrado Garantia Apple",
                                score: 98,
                                keywords: ["iPhone 14", "Pro Max"]
                            }
                        ]
                    });
                } else if (lastMessage.includes('description') || lastMessage.includes('descrição')) {
                    // Description might also expect JSON or plain text depending on service
                    // Based on TitleOptimizer pattern, let's assume JSON for consistency or check DescriptionOptimizer
                    // But for now, let's return a JSON that contains the description
                     content = JSON.stringify({
                        description: "Este é um iPhone 14 Pro Max incrível. Tela Super Retina XDR, câmera de 48MP e bateria para o dia todo. Produto original, novo e lacrado.",
                        features: ["Tela 6.7", "A16 Bionic"],
                        seo_score: 95
                    });
                } else if (lastMessage.includes('keyword')) {
                    content = JSON.stringify(["iphone 14", "apple", "smartphone", "ios", "pro max"]);
                }

                const response = {
                    id: 'chatcmpl-mock-mcp-' + Date.now(),
                    object: 'chat.completion',
                    created: Math.floor(Date.now() / 1000),
                    model: data.model || 'gpt-4o-mock',
                    choices: [
                        {
                            index: 0,
                            message: {
                                role: 'assistant',
                                content: content
                            },
                            finish_reason: 'stop'
                        }
                    ],
                    usage: {
                        prompt_tokens: 10,
                        completion_tokens: 20,
                        total_tokens: 30
                    }
                };

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify(response));
                console.log(`[MCP Server] Responded to: ${lastMessage.substring(0, 50)}...`);

            } catch (e) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'Invalid JSON' }));
            }
        });
    } else {
        res.writeHead(404);
        res.end('Not Found');
    }
});

server.listen(PORT, () => {
    console.log(`Mock MCP Server running on port ${PORT}`);
});
