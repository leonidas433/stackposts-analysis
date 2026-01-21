import argparse
import hashlib
import json
import math
import os
import re
import sys
from datetime import datetime, timezone
from pathlib import Path


def sha256_file(path: str) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def write_text_file(path: str, content: str) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "wb") as f:
        f.write(content.encode("utf-8", errors="replace"))


def write_json_file(path: str, payload: dict) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "wb") as f:
        f.write(
            json.dumps(payload, ensure_ascii=False, separators=(",", ":"), indent=2).encode(
                "utf-8"
            )
        )


TOKEN_RE = re.compile(r"[^\W\d_]+(?:['’][^\W\d_]+)?", re.UNICODE)
WS_RE = re.compile(r"\s+", re.UNICODE)


STOPWORDS_ES = {
    "a",
    "al",
    "algo",
    "como",
    "con",
    "de",
    "del",
    "desde",
    "donde",
    "el",
    "ella",
    "ellas",
    "ellos",
    "en",
    "entre",
    "era",
    "es",
    "esa",
    "ese",
    "eso",
    "esta",
    "este",
    "esto",
    "fue",
    "ha",
    "han",
    "hasta",
    "hay",
    "la",
    "las",
    "le",
    "les",
    "lo",
    "los",
    "mas",
    "me",
    "mi",
    "mis",
    "muy",
    "no",
    "nos",
    "o",
    "para",
    "pero",
    "por",
    "porque",
    "que",
    "se",
    "sin",
    "sobre",
    "su",
    "sus",
    "tambien",
    "te",
    "tu",
    "tus",
    "un",
    "una",
    "uno",
    "y",
    "ya",
}

STOPWORDS_EN = {
    "a",
    "about",
    "an",
    "and",
    "are",
    "as",
    "at",
    "be",
    "but",
    "by",
    "for",
    "from",
    "has",
    "have",
    "he",
    "her",
    "his",
    "i",
    "in",
    "is",
    "it",
    "its",
    "me",
    "my",
    "no",
    "not",
    "of",
    "on",
    "or",
    "our",
    "she",
    "so",
    "that",
    "the",
    "their",
    "them",
    "they",
    "this",
    "to",
    "too",
    "was",
    "we",
    "were",
    "with",
    "you",
    "your",
}


POSITIVE_ES = {
    "excelente",
    "genial",
    "perfecto",
    "perfecta",
    "amable",
    "amables",
    "rapido",
    "rapida",
    "rico",
    "rica",
    "recomendado",
    "recomendada",
    "recomiendo",
    "increible",
    "maravilloso",
    "maravillosa",
    "bueno",
    "buena",
    "buen",
    "mejor",
    "encanta",
    "encantó",
    "volveremos",
}

NEGATIVE_ES = {
    "malo",
    "mala",
    "horrible",
    "pesimo",
    "pésimo",
    "lento",
    "lenta",
    "frio",
    "fría",
    "sucio",
    "sucia",
    "caro",
    "cara",
    "ruido",
    "tarde",
    "fatal",
    "nunca",
    "no",
}

POSITIVE_EN = {
    "excellent",
    "great",
    "perfect",
    "amazing",
    "awesome",
    "friendly",
    "fast",
    "delicious",
    "recommended",
    "love",
    "loved",
    "best",
    "wonderful",
}

NEGATIVE_EN = {
    "bad",
    "terrible",
    "awful",
    "slow",
    "cold",
    "dirty",
    "expensive",
    "noise",
    "late",
    "never",
    "no",
    "not",
}


def normalize_text(s: str) -> str:
    return WS_RE.sub(" ", (s or "").strip())


def tokenize(s: str) -> list[str]:
    return [t.lower() for t in TOKEN_RE.findall(s or "")]


def detect_language(tokens: list[str]) -> str:
    if not tokens:
        return "und"

    es_hits = sum(1 for t in tokens if t in STOPWORDS_ES)
    en_hits = sum(1 for t in tokens if t in STOPWORDS_EN)

    if es_hits == 0 and en_hits == 0:
        return "und"

    if es_hits > en_hits:
        return "es"
    if en_hits > es_hits:
        return "en"
    return "und"


def lexicon_sentiment(tokens: list[str], lang: str) -> float:
    if not tokens:
        return 0.0

    if lang == "es":
        pos = POSITIVE_ES
        neg = NEGATIVE_ES
    elif lang == "en":
        pos = POSITIVE_EN
        neg = NEGATIVE_EN
    else:
        pos = POSITIVE_ES | POSITIVE_EN
        neg = NEGATIVE_ES | NEGATIVE_EN

    score_raw = 0
    for t in tokens:
        if t in pos:
            score_raw += 1
        elif t in neg:
            score_raw -= 1

    denom = math.sqrt(len(tokens))
    if denom <= 0:
        return 0.0
    return max(-1.0, min(1.0, score_raw / denom))


def rating_prior(rating: int) -> float:
    mapping = {1: -0.8, 2: -0.4, 3: 0.0, 4: 0.4, 5: 0.8}
    return mapping.get(int(rating or 0), 0.0)


def sentiment_label(score: float) -> str:
    if score >= 0.2:
        return "positive"
    if score <= -0.2:
        return "negative"
    return "neutral"


def build_topics(docs_tokens: list[list[str]]) -> dict:
    n = len(docs_tokens)
    if n == 0:
        return {"keywords": [], "bigrams": []}

    df: dict[str, int] = {}
    for tokens in docs_tokens:
        seen = set()
        for t in tokens:
            if len(t) < 3:
                continue
            if t in STOPWORDS_ES or t in STOPWORDS_EN:
                continue
            if t not in seen:
                df[t] = df.get(t, 0) + 1
                seen.add(t)

    def idf(term: str) -> float:
        return math.log((n + 1) / (df.get(term, 0) + 1)) + 1.0

    global_scores: dict[str, float] = {}
    for tokens in docs_tokens:
        filtered = [t for t in tokens if len(t) >= 3 and t not in STOPWORDS_ES and t not in STOPWORDS_EN]
        if not filtered:
            continue
        tf: dict[str, int] = {}
        for t in filtered:
            tf[t] = tf.get(t, 0) + 1
        total = sum(tf.values()) or 1
        for term, c in tf.items():
            score = (c / total) * idf(term)
            global_scores[term] = global_scores.get(term, 0.0) + score

    keywords = sorted(global_scores.items(), key=lambda kv: kv[1], reverse=True)[:15]

    bigram_counts: dict[str, int] = {}
    for tokens in docs_tokens:
        filtered = [t for t in tokens if len(t) >= 3 and t not in STOPWORDS_ES and t not in STOPWORDS_EN]
        for a, b in zip(filtered, filtered[1:]):
            key = f"{a} {b}"
            bigram_counts[key] = bigram_counts.get(key, 0) + 1

    bigrams = sorted(bigram_counts.items(), key=lambda kv: kv[1], reverse=True)[:15]

    return {
        "keywords": [{"term": t, "score": round(s, 6), "df": df.get(t, 0)} for t, s in keywords],
        "bigrams": [{"term": t, "count": c} for t, c in bigrams],
    }


def safe_int(v, default=0) -> int:
    try:
        return int(v)
    except Exception:
        return default


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--mode", default="prod")
    parser.add_argument("--input", required=True)
    args = parser.parse_args()

    engine_root = Path(__file__).resolve().parent
    src_dir = engine_root / "src"
    sys.path.insert(0, str(src_dir))

    try:
        from report_generator_professional import PIPELINE_VERSION  # type: ignore
    except Exception:
        PIPELINE_VERSION = "unknown"

    input_path = os.path.abspath(str(args.input))
    if not os.path.isfile(input_path):
        sys.stderr.write(f"input not found: {input_path}\n")
        return 2

    try:
        with open(input_path, "rb") as f:
            raw = f.read()
        payload = json.loads(raw.decode("utf-8", errors="replace"))
    except Exception as e:
        sys.stderr.write(f"invalid json input: {e}\n")
        return 3

    reviews = payload.get("reviews") if isinstance(payload, dict) else None
    if not isinstance(reviews, list):
        sys.stderr.write("input missing reviews array\n")
        return 4

    client_safe = Path(input_path).stem
    out_dir = engine_root / "data" / "reports" / client_safe / f"v{PIPELINE_VERSION}"
    os.makedirs(out_dir, exist_ok=True)

    per_review = []
    docs_tokens: list[list[str]] = []

    rating_counts = {str(i): 0 for i in range(1, 6)}
    lang_counts = {"es": 0, "en": 0, "und": 0}
    sent_counts = {"positive": 0, "neutral": 0, "negative": 0}
    sent_scores = []
    with_reply = 0

    for idx, r in enumerate(reviews):
        if not isinstance(r, dict):
            continue

        rating = safe_int(r.get("rating"), 0)
        if str(rating) in rating_counts:
            rating_counts[str(rating)] += 1

        text = normalize_text(str(r.get("text") or ""))
        response = normalize_text(str(r.get("responseFromOwnerText") or ""))
        if response:
            with_reply += 1

        tokens = tokenize(text)
        lang = detect_language(tokens)
        lang_counts[lang] = lang_counts.get(lang, 0) + 1

        lex = lexicon_sentiment(tokens, lang)
        combined = max(-1.0, min(1.0, (lex + rating_prior(rating)) / 2.0))
        label = sentiment_label(combined)
        sent_counts[label] = sent_counts.get(label, 0) + 1
        sent_scores.append(combined)

        docs_tokens.append(tokens)

        unique = len(set(tokens))
        total = len(tokens)
        complexity = (unique / total) if total else 0.0

        date_str = str(r.get("date") or "")
        ymd = None
        try:
            dt = datetime.strptime(date_str, "%Y-%m-%d %H:%M:%S")
            ymd = dt.strftime("%Y-%m-%d")
        except Exception:
            ymd = None

        per_review.append(
            {
                "i": idx,
                "rating": rating,
                "date": date_str,
                "date_ymd": ymd,
                "language": lang,
                "sentiment": {"label": label, "score": round(combined, 6)},
                "metrics": {
                    "text_chars": len(text),
                    "word_count": total,
                    "unique_words": unique,
                    "lexical_complexity": round(complexity, 6),
                    "has_owner_response": bool(response),
                    "owner_response_chars": len(response),
                },
                "text_preview": text[:240],
            }
        )

    topics = build_topics(docs_tokens)
    n_reviews = len(per_review)
    avg_sent = sum(sent_scores) / n_reviews if n_reviews else 0.0
    reply_rate = (with_reply / n_reviews * 100.0) if n_reviews else 0.0

    analysis = {
        "pipeline_version": str(PIPELINE_VERSION),
        "generated_at": datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
        "mode": str(args.mode or "prod"),
        "input": {
            "address": payload.get("address") if isinstance(payload, dict) else None,
            "category": payload.get("category") if isinstance(payload, dict) else None,
            "totalScore": payload.get("totalScore") if isinstance(payload, dict) else None,
            "reviewsCount": payload.get("reviewsCount") if isinstance(payload, dict) else None,
            "website": payload.get("website") if isinstance(payload, dict) else None,
        },
        "stats": {
            "reviews": n_reviews,
            "rating": rating_counts,
            "language": lang_counts,
            "sentiment": {
                "avg_score": round(avg_sent, 6),
                "positive": sent_counts.get("positive", 0),
                "neutral": sent_counts.get("neutral", 0),
                "negative": sent_counts.get("negative", 0),
            },
            "reply_rate_pct": round(reply_rate, 2),
        },
        "topics": topics,
        "reviews": per_review,
    }

    analysis_path = str(out_dir / "analysis.json")
    write_json_file(analysis_path, analysis)

    docx_path = str(out_dir / f"{client_safe}_informe_PROFESIONAL.docx")
    pdf_path = str(out_dir / f"{client_safe}_informe_PROFESIONAL.pdf")

    header = f"Analytee Report (pipeline v{PIPELINE_VERSION})\nGenerated: {analysis['generated_at']}\nReviews: {n_reviews}\n"
    summary = json.dumps(
        {"stats": analysis["stats"], "topics": analysis["topics"]},
        ensure_ascii=False,
        separators=(",", ":"),
        indent=2,
    )
    content = header + "\n" + summary + "\n"
    write_text_file(docx_path, content)
    write_text_file(pdf_path, content)

    execution_log_path = str(out_dir / "execution_log.json")
    log_payload = {
        "pipeline_version": str(PIPELINE_VERSION),
        "mode": str(args.mode or "prod"),
        "created_at": analysis["generated_at"],
        "input_hash": sha256_file(input_path),
        "docx_hash": sha256_file(docx_path),
        "pdf_hash": sha256_file(pdf_path),
        "analysis_hash": sha256_file(analysis_path),
        "counts": {
            "reviews": n_reviews,
            "with_owner_response": with_reply,
        },
    }
    write_json_file(execution_log_path, log_payload)

    sys.stdout.write(json.dumps({"status": "ok", "out_dir": str(out_dir)}, ensure_ascii=False) + "\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
