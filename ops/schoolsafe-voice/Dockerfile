# Voix de JASPE (03/11) : Piper (moteur local, hors ligne) + voix francaise ; reseau interne seulement.
FROM python:3.11-slim
COPY build/piper/ /opt/piper/
COPY build/voices/ /opt/voices/
COPY server.py /srv/server.py
ENV PIPER_BIN=/opt/piper/piper PIPER_MODEL=/opt/voices/fr_FR-siwis-medium.onnx VOICE_TOKEN_FILE=/run/voice-token
USER 33:33
EXPOSE 18893
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD python3 -c "import urllib.request,sys;sys.exit(0 if b'true' in urllib.request.urlopen('http://127.0.0.1:18893/health',timeout=4).read() else 1)"
CMD ["python3", "/srv/server.py"]
