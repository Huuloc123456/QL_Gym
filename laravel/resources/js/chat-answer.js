const cleanText = (text) => text
    .replace(/\*\*(.*?)\*\*/g, '$1')
    .replace(/__(.*?)__/g, '$1')
    .trim();

export function parseAssistantAnswer(answer) {
    const normalizedAnswer = String(answer ?? '')
        .replace(/\r\n?/g, '\n')
        .replace(/([.!?])\s+-\s+/g, '$1\n- ')
        .trim();
    const blocks = [];
    let currentList = null;

    normalizedAnswer.split('\n').forEach((line) => {
        const content = line.trim();
        if (!content) {
            currentList = null;
            return;
        }

        const bullet = content.match(/^[-*•]\s+(.+)$/);
        if (bullet) {
            if (!currentList) {
                currentList = { type: 'list', items: [] };
                blocks.push(currentList);
            }
            currentList.items.push(cleanText(bullet[1]));
            return;
        }

        currentList = null;
        blocks.push({ type: 'paragraph', text: cleanText(content) });
    });

    return blocks;
}
