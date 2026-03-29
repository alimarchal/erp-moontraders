joh is sheet mein https://moontraders.test/reports/investment-summary hai us mein Expenses Detail hai us ka report ke tra system banawo

https://moontraders.test/reports/claim-register make sure yeh jab add krin tou date ke input ho phr category ke

category kse file sa lana yeh categoreis hain
Stationary
TCS
Tonner & IT
Salaries
Fuel
Van Work

aur mry Chart of acocunt ko bhi dekho aur us k relavent us mein add krna poray double entry system ho cross database compatability psql/mariadb/mysql

aur code bhi us file mein lagana ks ko credit aur ks ko debit krna hai auto ho jab post ho thek wsay he jsay claim register hai seprate table mein store ho us mein yeh hona chieyay

            $table->userTracking();
            $table->softDeletes();

user ke tracking tract already add hai aur transaction use krna proper ly aur prevent from double clikcing aur component wo he use krna joh claim register mein hain
agar Fuel ho tou yeh inputs ane chieyay

VAN# yeh select2 class k sath van load krna humay pas joh vehicles hain phr vehicle type jab database mein entry kro tou yeh khod he kr dena yeh user sa puchnay ke zarorart nhi hai q k vehicle Vehicle Type already hai phr driver name joh humary employee hain Driver ka designation walay person utana yeh meery seeder mein hai employee waly mein aur liter aur amount puchay

agar TCS ho srf amount date tou ho ge he like cliam register
agar Salaries hou tou employee_no, aur employee name auto add krn dena aur phr us k bad us ke amount

agar fuel ho tou srf amount

Tonner & IT ho tou srf amount

make sure yeh post bhi houn aur edit bhi aur delete bhi agar posted houn tou nhi hona chieyay is k relavent permssion aur middleware restriction bhi lagana

phr yeh report check krna expense https://moontraders.test/reports/investment-summary us date tak ka ho us month mein mean jab report load ho tou us month ke starting date sa us current date tak ka sara expense is mein ho gah aur is k related yeh report ko bhi dekho ta k expense sae sa kam kry

aur jab is expense k table mein double entry walay sysetm ho ta k sae balance track calculated ho is ka balance calcuate ho dekhna yah koi garbar nhi hone chieyay
