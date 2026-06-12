/** Placeholder temporário para pontos de abreviações (ex.: Prof.). */
const PERIOD_PLACEHOLDER = '\uE000'

/** Títulos e abreviações comuns em nomes gerados pelo backend. */
const PROTECTED_ABBREVIATIONS =
  /\b(Prof|Dr|Mr|Mrs|Ms|Sr|Sra|Dra|Jr|PhD|MSc|BSc|Eng|Av|Des)\./gi

/**
 * Divide o corpo do bulletin em frases, sem quebrar abreviações como "Prof.".
 */
export function splitBulletinSentences(text: string): string[] {
  const protectedText = text.replace(
    PROTECTED_ABBREVIATIONS,
    match => match.replace('.', PERIOD_PLACEHOLDER),
  )

  return protectedText
    .split(/\.\s+/)
    .map(part => part.split(PERIOD_PLACEHOLDER).join('.').trim().replace(/\.$/, ''))
    .filter(Boolean)
}

/** Remove prefixo legado "Time A 1×1 Time B:" quando existir. */
export function extractBulletinBody(content: string): string {
  return content.replace(/^.+\s\d+[×xX]\d+\s.+:\s+/u, '')
}
