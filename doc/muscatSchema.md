# Muscat schema

As of: Wed, 25 Oct 2017

The following is the database definition format of the Muscat database (BCPL).

An equivalent representation as used in the conversion program is at /tables/muscatSchema.xml


```
rec (q0 qp art doc ser acq acc status)
q0 = i
qp = s
ag (a ca ad al aff)
a (n1 n2 nd nt)
ca (n1 n2 nd nt)
ad = s
al (n1 n2 nd nt)
aff = s
tg (t tt to ta tc lang lpt lto)
t = s
tt = s
to = s
ta = s
tc = s
lang = s
r = s
e (role n)
edn = s
ee (role n)
vno = s
pg (pl pu)
pl = s
pu = s
d = s
v = s
p = s
form = s
size = s
ts = s
isbn = s
issn = s
pt = s
doi (doifld doslink winlink)
doifld = s
role = s
n (n1 n2 nd nt)
n1 = s
n2 = s
nd = s
art (ag tg e p in j abs notes url urlft k k2 rpl loc lab)
doc (ag tg e edn ee pg d v p form size ts isbn issn pt doi abs notes url 
urlft
k k2 rpl loc lab)
ser (ag tg r pg form size issn pt ft st freq abs notes url urlft k k2 
loc
lab hold)
in (ag tg edn vno pg d form ts isbn issn pt doi)
j (tg pg d form issn pt doi)
ft = s
st = s
freq = s
abs = s
notes (note local priv)
note = s
local = s
priv = s
url (urlgen doslink winlink)
urlft (urlfull doslink winlink)
urlgen = s
urlfull = s
k (ks kw)
ks = s
kw = s
k2 (ka kb kc kd ke kf kg)
ka = s
kb = s
kc = s
kd = s
ke = s
kf = s
kg = s
rpl = s
loc (location doslink winlink)
location = s
doslink = s
winlink = s
lab = s
z0 = s
z1 = s
z2 = s
z3 = s
z4 = s
z5 = s
z6 = s
z7 = s
z8 = s
z9 = s
acq (ref date o sref pr fund recr)
acc (ref date con recr)
ref = s
date = s
o = s
sref = s
pr = s
fund = s
con = s
recr = s
status = s
hold = s
lpt = s
nt = s
lto = s
```
