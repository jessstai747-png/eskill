import { test, expect } from '@playwright/test';

test.describe('Render API (Harness Mode)', () => {
    const testToken = 'test-token';

    test('should create render job with harness', async ({ request }) => {
        const response = await request.post('/api/render', {
            headers: {
                'Authorization': `Bearer ${testToken}`,
                'Content-Type': 'application/json',
            },
            data: {
                audio: 'test-audio.mp3',
                config: {
                    duration: 1,
                    format: 'mp4',
                },
            },
        });

        expect(response.ok()).toBeTruthy();
        expect(response.status()).toBe(200);

        const data = await response.json();
        expect(data).toHaveProperty('success', true);
        expect(data).toHaveProperty('jobId');
        expect(data).toHaveProperty('status', 'completed');
        expect(data).toHaveProperty('videoUrl');
        expect(data.metadata).toHaveProperty('harness', true);
    });

    test('should reject request without audio', async ({ request }) => {
        const response = await request.post('/api/render', {
            headers: {
                'Authorization': `Bearer ${testToken}`,
                'Content-Type': 'application/json',
            },
            data: {
                config: {
                    duration: 1,
                },
            },
        });

        expect(response.status()).toBe(400);

        const data = await response.json();
        expect(data).toHaveProperty('success', false);
        expect(data).toHaveProperty('error');
    });

    test('should reject request without auth token', async ({ request }) => {
        const response = await request.post('/api/render', {
            headers: {
                'Content-Type': 'application/json',
            },
            data: {
                audio: 'test-audio.mp3',
            },
        });

        expect(response.status()).toBe(401);
    });

    test('should reject request with invalid token', async ({ request }) => {
        const response = await request.post('/api/render', {
            headers: {
                'Authorization': 'Bearer invalid-token',
                'Content-Type': 'application/json',
            },
            data: {
                audio: 'test-audio.mp3',
            },
        });

        expect(response.status()).toBe(401);
    });

    test('should get job status', async ({ request }) => {
        // First create a job
        const createResponse = await request.post('/api/render', {
            headers: {
                'Authorization': `Bearer ${testToken}`,
                'Content-Type': 'application/json',
            },
            data: {
                audio: 'test-audio.mp3',
            },
        });

        const createData = await createResponse.json();
        const jobId = createData.jobId;

        // Then check status
        const statusResponse = await request.get(`/api/render/${jobId}`, {
            headers: {
                'Authorization': `Bearer ${testToken}`,
            },
        });

        expect(statusResponse.ok()).toBeTruthy();

        const statusData = await statusResponse.json();
        expect(statusData).toHaveProperty('success', true);
        expect(statusData).toHaveProperty('jobId', jobId);
        expect(statusData).toHaveProperty('status', 'completed');
        expect(statusData).toHaveProperty('progress', 100);
    });

    test('should cleanup old renders', async ({ request }) => {
        const response = await request.delete('/api/render/cleanup', {
            headers: {
                'Authorization': `Bearer ${testToken}`,
            },
        });

        expect(response.ok()).toBeTruthy();

        const data = await response.json();
        expect(data).toHaveProperty('success', true);
        expect(data).toHaveProperty('cleaned');
        expect(typeof data.cleaned).toBe('number');
    });
});
