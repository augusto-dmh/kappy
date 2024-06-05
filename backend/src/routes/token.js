import { Router } from "express";
import tokenController from "../controllers/token";
import { body } from "express-validator";
import * as validations from "../validation";
import * as errors from "../validation/errors/models";

const router = new Router();

const fieldsValidation = [
  [body("nickname").notEmpty().withMessage(errors.nickname.empty).escape()],
  [body("password").notEmpty().withMessage(errors.password.empty).escape()],
];

router.post("/tokens", fieldsValidation, tokenController.store);

export default router;
