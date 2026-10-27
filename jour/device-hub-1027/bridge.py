# -*- coding: utf-8 -*-
"""
Pont local SchoolSafe — appareil Hikvision -> serveur app.shoolsafe.tech.

Un passage = un tirage des evenements de presence de l'appareil (192.168.0.200,
identifiants digest) + poussée au recepteur SchoolSafe (HTTPS, jeton).
Cree pour tourner 1 fois par minute (Planificateur Windows) : aucun processus
resident, adapte aux machines a RAM limitee.

Regles :
 - anti-doublon cote serveur : re-pousser un evenement deja recu = doublon (200)
 - le curseur (state.json) repart de 5 minutes en arriere pour ne rien perdre
 - apres une coupure internet, le tirage rattrape la plage manquee (l'appareil
   garde ses evenements en memoire)
 - tout est journalise dans bridge.log
"""
import json
import os
import subprocess
import sys
from datetime import datetime, timedelta

DEVICE = "http://192.168.0.200"
DEVICE_USER = "admin"
DEVICE_PASS = "Loms@1190"
PUSH_URL = ("https://app.shoolsafe.tech/schoolsafe-device-push.php?token="
            "de1042c5c9f4148ade078ccb8d82ac1c19bbe912985dfad2a65ffc4db7b7fd91")
ENROLL_URL = ("https://app.shoolsafe.tech/schoolsafe-device-enrollstatus.php?token="
              "de1042c5c9f4148ade078ccb8d82ac1c19bbe912985dfad2a65ffc4db7b7fd91")
ICI = os.path.dirname(os.path.abspath(__file__))
STATE = os.path.join(ICI, "state.json")
LOG = os.path.join(ICI, "bridge.log")
RECUL = timedelta(minutes=60)     # re-tirage de surete (doublons absorbes ;
                                  # l'index de l'appareil repond avec retard)


def log(msg):
    with open(LOG, "a", encoding="utf-8") as f:
        f.write(datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " " + msg + "\n")


def charger_curseur():
    if os.path.isfile(STATE):
        try:
            with open(STATE, encoding="utf-8") as f:
                return datetime.fromisoformat(json.load(f)["last_time"])
        except Exception:
            pass
    return None  # premier passage : la derniere heure est 0


def sauver_curseur(moment):
    with open(STATE, "w", encoding="utf-8") as f:
        json.dump({"last_time": moment.isoformat()}, f)


def tirer_debut_fin():
    now = datetime.now().astimezone()
    dernier = charger_curseur()
    if dernier is None:
        debut = now - timedelta(hours=24)  # premier passage : les 24 dernieres heures
    else:
        debut = max(dernier - RECUL, now - timedelta(days=45))  # l'appareil garde ~45 j
    return debut, now + timedelta(minutes=1)


def tirer_appareil(debut, fin):
    """Tirage pagine : tant que l'appareil dit MORE, on continue (max 5 pages)."""
    toutes = {"AcsEvent": {"InfoList": []}}
    position = 0
    for _page in range(5):
        cond = {"AcsEventCond": {
            "searchID": "pont" + datetime.now().strftime("%H%M%S%f"),
            "searchResultPosition": position, "maxResults": 100,
            "major": 5, "minor": 38,
            "startTime": debut.isoformat(timespec="seconds"),
            "endTime": fin.isoformat(timespec="seconds")}}
        r = subprocess.run(
            ["curl", "-s", "--digest", "-u", DEVICE_USER + ":" + DEVICE_PASS,
             "--connect-timeout", "5", "--max-time", "25",
             "-X", "POST", "-H", "Content-Type: application/json",
             "--data-binary", json.dumps(cond),
             DEVICE + "/ISAPI/AccessControl/AcsEvent?format=json"],
            capture_output=True, text=True, encoding="utf-8", errors="replace")
        if r.returncode != 0 or not r.stdout.strip():
            raise RuntimeError("appareil inaccessible (code %s)" % r.returncode)
        reponse = json.loads(r.stdout)
        bloc = reponse.get("AcsEvent") or {}
        infos = bloc.get("InfoList") or []
        toutes["AcsEvent"]["InfoList"].extend(infos)
        if bloc.get("responseStatusStrg") != "MORE" or not infos:
            break
        position += len(infos)
    return toutes


def normaliser(reponse):
    info = (reponse.get("AcsEvent") or {}).get("InfoList") or []
    evenements = []
    for e in info:
        t = (e.get("time") or "").strip()
        emp = (e.get("employeeNoString") or "").strip()
        statut = (e.get("attendanceStatus") or "").lower()
        if not t or not emp:
            continue
        sens = "out" if statut.endswith("out") else "in"
        sid = str(e.get("serialNo") or "hik-" + hashlib.sha1(
            (emp + "|" + t).encode()).hexdigest()[:32])
        evenements.append({"id": sid, "employee_id": emp, "time": t, "direction": sens})
    return evenements


def pousser(evenements):
    r = subprocess.run(
        ["curl", "-s", "--connect-timeout", "10", "--max-time", "30",
         "-X", "POST", "-H", "Content-Type: application/json",
         "--data-binary", json.dumps({"events": evenements}), PUSH_URL],
        capture_output=True, text=True, encoding="utf-8", errors="replace")
    if r.returncode != 0:
        raise RuntimeError("serveur inaccessible (code %s)" % r.returncode)
    return json.loads(r.stdout)


def sync_enroll():
    """Remonte le nombre d'empreintes de chaque utilisateur du terminal
    (l'application affiche alors « Empreintes : N/5 » par personnel)."""
    cond = {"UserInfoSearchCond": {
        "searchID": "pont" + datetime.now().strftime("%H%M%S%f"),
        "searchResultPosition": 0, "maxResults": 100}}
    r = subprocess.run(
        ["curl", "-s", "--digest", "-u", DEVICE_USER + ":" + DEVICE_PASS,
         "--connect-timeout", "5", "--max-time", "25",
         "-X", "POST", "-H", "Content-Type: application/json",
         "--data-binary", json.dumps(cond),
         DEVICE + "/ISAPI/AccessControl/UserInfo/Search?format=json"],
        capture_output=True, text=True, encoding="utf-8", errors="replace")
    if r.returncode != 0 or not r.stdout.strip():
        raise RuntimeError("appareil inaccessible pour l'enrolement")
    reponse = json.loads(r.stdout)
    utilisateurs = (reponse.get("UserInfoSearch") or {}).get("UserInfo") or []
    users = [{"employee_id": str(u.get("employeeNo") or ""),
              "num_fp": int(u.get("numOfFP") or 0)}
             for u in utilisateurs if str(u.get("employeeNo") or "")]
    if not users:
        return 0
    r = subprocess.run(
        ["curl", "-s", "--connect-timeout", "10", "--max-time", "30",
         "-X", "POST", "-H", "Content-Type: application/json",
         "--data-binary", json.dumps({"users": users}), ENROLL_URL],
        capture_output=True, text=True, encoding="utf-8", errors="replace")
    if r.returncode != 0:
        raise RuntimeError("serveur inaccessible pour l'enrolement")
    return json.loads(r.stdout).get("maj", 0)


def main():
    debut, fin = tirer_debut_fin()
    reponse = tirer_appareil(debut, fin)
    evenements = normaliser(reponse)
    try:
        enrol = sync_enroll()
    except Exception as ex:
        enrol = "erreur: " + str(ex)
    if not evenements:
        sauver_curseur(fin.replace(microsecond=0))
        log("OK 0 evenement enrol=%s" % enrol)
        return
    resultat = pousser(evenements)
    if resultat.get("error"):
        raise RuntimeError("serveur a refuse : %s" % resultat["error"])
    dernier = max(datetime.fromisoformat(e["time"]) for e in evenements)
    sauver_curseur(fin.replace(microsecond=0))
    log("OK %s pousses (%s doublons) dernier=%s enrol=%s" % (
        resultat.get("recus", 0), resultat.get("doublons", 0),
        dernier.strftime("%H:%M:%S"), enrol))


if __name__ == "__main__":
    try:
        main()
    except Exception as ex:
        log("ERREUR " + str(ex))
        sys.exit(0)  # le passage suivant retentera ; jamais de code d'erreur bruyant
