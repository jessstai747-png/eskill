# Setup AI & Intelligence Keys

The following features have been implemented but require API keys to function correctly.

## 1. OpenAI Vision (Image Intelligence)
Used by `ImageKiller` to analyze image quality, detect watermarks, and ensure white backgrounds.

**Required Variable:** `OPENAI_API_KEY`

1.  Get your key at [platform.openai.com](https://platform.openai.com/api-keys).
2.  Ensure your account has access to `gpt-4o` or `gpt-4-turbo`.
3.  Add to `.env`:
    ```env
    OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxxxxxxxxxxxxx
    ```

## 2. Remove.bg (Background Removal)
Used by `ImageOptimizer` to automatically remove backgrounds from product images.

**Required Variable:** `REMOVE_BG_API_KEY`

1.  Get your key at [remove.bg/api](https://www.remove.bg/api).
2.  Add to `.env`:
    ```env
    REMOVE_BG_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxx
    ```

## 3. Verification
After adding the keys, run the test script again to verify:
```bash
php scripts/test_image_intelligence.php
```
